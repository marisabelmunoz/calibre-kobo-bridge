<?php

namespace CalibreOpds;

class Opds
{
    private const PREFERRED = ['epub', 'kepub', 'mobi', 'azw3', 'pdf'];


    public static function fetch(string $url): string|false
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => Config::get('NC_USER') . ':' . Config::get('NC_PASS'),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'OCS-APIREQUEST: true',
                'Accept: application/atom+xml, application/xml, text/xml',
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return ($err || $code >= 400) ? false : $body;
    }


    public static function parse(string $xml): array
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$doc) return ['title' => 'Parse error', 'entries' => [], 'nav' => []];

        $title   = (string)($doc->title ?? 'Catalog');
        $entries = [];
        $nav     = [];

        foreach ($doc->link ?? [] as $link) {
            $rel = (string)$link['rel'];
            if (in_array($rel, ['up', 'start', 'self'], true)) {
                $nav[$rel] = ['href' => (string)$link['href'], 'title' => (string)$link['title']];
            }
        }

        foreach ($doc->entry ?? [] as $entry) {
            $e = [
                'id'      => (string)$entry->id,
                'title'   => (string)$entry->title,
                'author'  => '',
                'summary' => '',
                'cover'   => '',
                'links'   => [],
                'type'    => 'navigation',
            ];

            $authors = [];
            foreach ($entry->author ?? [] as $a) $authors[] = (string)$a->name;
            $e['author']  = implode(', ', $authors);
            $e['summary'] = trim(strip_tags((string)($entry->summary ?? $entry->content ?? '')));

            foreach ($entry->link ?? [] as $lk) {
                $rel  = (string)$lk['rel'];
                $type = (string)$lk['type'];
                $href = (string)$lk['href'];

                if (str_contains($type, 'opds-catalog') || str_contains($rel, 'subsection') || $rel === 'related') {
                    $e['type']    = 'navigation';
                    $e['links'][] = ['rel' => $rel, 'type' => $type, 'href' => $href];
                } elseif (
                    str_contains($rel, 'acquisition') ||
                    str_contains($type, 'epub') || str_contains($type, 'mobi') ||
                    str_contains($type, 'pdf')  || str_contains($type, 'kepub') ||
                    str_contains($type, 'azw')
                ) {
                    $e['type']    = 'book';
                    $ext          = self::guessExt($type, $href);
                    $e['links'][] = ['rel' => 'acquisition', 'type' => $type, 'href' => $href, 'ext' => $ext];
                } elseif (str_contains($rel, 'thumbnail') || str_contains($rel, 'image')) {
                    $e['cover'] = $href;
                }
            }

            $entries[] = $e;
        }

        return ['title' => $title, 'entries' => $entries, 'nav' => $nav];
    }


    public static function searchUrl(string $query): string
    {
        $tmpl = self::searchTemplate();
        return str_replace(
            ['{searchTerms}', '%7BsearchTerms%7D'],
            rawurlencode($query),
            $tmpl
        );
    }

    private static function searchTemplate(): string
    {
        static $tmpl = null;
        if ($tmpl !== null) return $tmpl;

        $rootXml = self::fetch(Config::get('OPDS_BASE'));
        if ($rootXml) {
            libxml_use_internal_errors(true);
            $doc = simplexml_load_string($rootXml, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($doc) {
                foreach ($doc->link ?? [] as $lk) {
                    if ((string)$lk['rel'] !== 'search') continue;
                    $href = (string)$lk['href'];
                    $type = (string)$lk['type'];

                    if (str_contains($type, 'opensearch')) {
                        $osd = self::fetch(self::absoluteUrl($href));
                        if ($osd) {
                            libxml_use_internal_errors(true);
                            $osdoc = simplexml_load_string($osd, 'SimpleXMLElement', LIBXML_NOCDATA);
                            if ($osdoc) {
                                $best = null;
                                $bestScore = -1;
                                foreach ($osdoc->Url ?? [] as $u) {
                                    $t = (string)$u['template'];
                                    if (!$t) continue;
                                    $s = (str_contains((string)$u['type'], 'atom') ? 2 : 0)
                                        + (str_contains((string)$u['type'], 'opds') ? 2 : 0);
                                    if ($s > $bestScore) {
                                        $best = $t;
                                        $bestScore = $s;
                                    }
                                }
                                if ($best) {
                                    $tmpl = self::absoluteUrl($best);
                                    return $tmpl;
                                }
                            }
                        }
                    }

                    if (str_contains($href, '{searchTerms}') || str_contains($href, '{')) {
                        $tmpl = self::absoluteUrl($href);
                        return $tmpl;
                    }
                    $tmpl = self::absoluteUrl($href);
                    return $tmpl;
                }
            }
        }

        $tmpl = rtrim(Config::get('OPDS_BASE'), '/') . '/search/{searchTerms}';
        return $tmpl;
    }


    public static function preferredLink(array $links): ?array
    {
        usort($links, function ($a, $b) {
            $pa = array_search($a['ext'] ?? '', self::PREFERRED);
            $pb = array_search($b['ext'] ?? '', self::PREFERRED);
            return (($pa === false) ? 99 : $pa) <=> (($pb === false) ? 99 : $pb);
        });
        return $links[0] ?? null;
    }

    public static function absoluteUrl(string $href): string
    {
        if (str_starts_with($href, 'http')) return $href;
        $base = rtrim(Config::get('OPDS_BASE'), '/');
        if (str_starts_with($href, '/')) {
            $p = parse_url($base);
            return $p['scheme'] . '://' . $p['host'] . $href;
        }
        return $base . '/' . ltrim($href, '/');
    }

    private static function guessExt(string $mime, string $href): string
    {
        $map = ['epub' => 'epub', 'mobi' => 'mobi', 'kepub' => 'kepub', 'azw' => 'azw3', 'pdf' => 'pdf', 'fb2' => 'fb2', 'cbz' => 'cbz'];
        foreach ($map as $k => $v) {
            if (str_contains($mime, $k) || str_contains($href, $k)) return $v;
        }
        $ext = strtolower(pathinfo(parse_url($href, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        return $ext ?: 'bin';
    }


    public static function handleAction(string $action): void
    {
        match ($action) {
            'img'       => self::actionImg(),
            'download'  => self::actionDownload(),
            'kobo'      => self::actionKobo(),
            'add_link'  => self::actionAddLink(),
            default     => (function () {
                http_response_code(404);
                exit('Unknown action');
            })(),
        };
    }

    private static function actionImg(): void
    {
        $href = Navigation::dec($_GET['href'] ?? '');
        if (!$href) {
            http_response_code(400);
            exit;
        }

        $ch = curl_init(self::absoluteUrl($href));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => Config::get('NC_USER') . ':' . Config::get('NC_PASS'),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $data = curl_exec($ch);
        $mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$data || $code >= 400) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . ($mime ?: 'image/jpeg'));
        header('Cache-Control: max-age=86400, private');
        echo $data;
        exit;
    }

    private static function actionDownload(): void
    {
        $href = Navigation::dec($_POST['href'] ?? Navigation::dec($_GET['href'] ?? ''));
        $name = $_POST['name'] ?? $_GET['name'] ?? 'book.epub';
        if (!$href) {
            http_response_code(400);
            exit('Missing href');
        }

        $ch = curl_init(self::absoluteUrl($href));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => Config::get('NC_USER') . ':' . Config::get('NC_PASS'),
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'CalibreOPDS/2.0',
            CURLOPT_HTTPHEADER     => [
                'OCS-APIREQUEST: true',
                'Accept: application/epub+zip, application/octet-stream, */*',
            ],
        ]);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if (!$data || $code >= 400) {
            http_response_code(502);
            exit('Download failed (HTTP ' . $code . ')');
        }

        $safe = preg_replace('/[^\p{L}\p{N}.\-_ ]/u', '', $name) ?: 'book.epub';
        header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $safe . '"');
        header('Content-Length: ' . strlen($data));
        echo $data;
        exit;
    }

    private static function actionKobo(): void
{
    header('Content-Type: application/json');

    // Get POST data
    $href   = Navigation::dec($_POST['href'] ?? '');
    $name   = $_POST['name']   ?? 'book.epub';
    $title  = $_POST['title']  ?? $name;
    $author = $_POST['author'] ?? 'Unknown';

    if (!$href) {
        echo json_encode(['error' => 'Missing href']);
        exit;
    }

    // 🔧 FIX: Convert relative URL to absolute URL
    $absoluteHref = self::absoluteUrl($href);
    
    // Create queue item with absolute URL
    $item = [
        'file'   => $name,
        'url'    => $absoluteHref,  // Store absolute URL instead of relative
        'title'  => $title,
        'author' => $author,
        'ext'    => strtolower(pathinfo($name, PATHINFO_EXTENSION)),
        'added'  => date('Y-m-d H:i:s'),
    ];

    // Add to queue using the Queue class
    if (Queue::add($item)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Could not add to queue']);
    }
    exit;
}


    private static function actionAddLink(): void
    {
        header('Content-Type: application/json');

        $key = $_GET['key'] ?? $_POST['key'] ?? '';
        if (!hash_equals(Config::get('API_KEY'), $key)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $url    = $_GET['url']    ?? $_POST['url']    ?? '';
        $name   = $_GET['name']   ?? $_POST['name']   ?? 'book.epub';
        $title  = $_GET['title']  ?? $_POST['title']  ?? $name;
        $author = $_GET['author'] ?? $_POST['author'] ?? 'Unknown';

        if (!$url) {
            http_response_code(400);
            echo json_encode(['error' => 'url is required']);
            exit;
        }

        $queueFile = dirname(__DIR__) . '/data/queue.json';
        $queue     = file_exists($queueFile)
            ? (json_decode((string)file_get_contents($queueFile), true) ?? [])
            : [];

        $entry = [
            'id'     => uniqid('', true),
            'url'    => $url,
            'name'   => $name,
            'title'  => $title,
            'author' => $author,
            'added'  => date('c'),
        ];

        array_unshift($queue, $entry);
        $queue = array_slice($queue, 0, 100); // cap at 100 entries

        file_put_contents($queueFile, json_encode($queue, JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true, 'id' => $entry['id'], 'title' => $title]);
        exit;
    }
}
