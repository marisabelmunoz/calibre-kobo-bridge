<?php

/**
 * api.php — Kobo JSON API
 *
 * All requests require ?key=<API_KEY>
 *
 * GET  ?action=list                     → JSON array of queued books
 * GET  ?action=add_link&url=…&name=…    → Add a book link to the queue
 * GET  ?action=remove&index=N           → Remove item by index
 * GET  ?action=clear                    → Clear the entire queue
 * GET  ?action=get_queue                → Alias for list
 */

declare(strict_types=1);

define('_CALIBRE_OPDS', true);
require_once __DIR__ . '/bootstrap.php';

use CalibreOpds\Config;
use CalibreOpds\Auth;
use CalibreOpds\Queue;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

Config::load();



// ── Auth ──────────────────────────────────────────────────────────────────────
$key = $_GET['key'] ?? '';
if (!Auth::checkApiKey($key)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = strtolower(trim($_GET['action'] ?? 'list'));

switch ($action) {

	case 'list':
   	 case 'get_queue':
        echo json_encode(Queue::load(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    case 'add_link':
        $url = trim($_GET['url'] ?? '');
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid URL required']);
            exit;
        }
        $name = basename($_GET['name'] ?? 'book.epub');
        $item = [
            'file'   => $name,
            'url'    => $url,
            'title'  => trim($_GET['title']  ?? $name),
            'author' => trim($_GET['author'] ?? 'Unknown'),
            'ext'    => strtolower(pathinfo($name, PATHINFO_EXTENSION)),
            'added'  => date('Y-m-d H:i:s'),
        ];
        if (Queue::add($item)) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Could not write queue file']);
        }
        break;

    case 'remove':
        $index = (int)($_GET['index'] ?? -1);
        if ($index < 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid index']);
            exit;
        }
        echo json_encode(['success' => Queue::remove($index)]);
        break;

    case 'clear':
    case 'clear_queue':
        echo json_encode(['success' => Queue::clear()]);
        break;

    case 'mark_synced':
        $index = (int)($_GET['index'] ?? -1);
        if ($index < 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid index']);
            exit;
        }

        $queue = Queue::load();
        if (isset($queue[$index])) {
            $queue[$index]['synced'] = date('Y-m-d H:i:s');
            $queue[$index]['synced_status'] = 'success';
            Queue::save($queue);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Item not found']);
        }
        break;

    case 'mark_failed':
        $index = (int)($_GET['index'] ?? -1);
        if ($index < 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid index']);
            exit;
        }

        $queue = Queue::load();
        if (isset($queue[$index])) {
            $queue[$index]['synced'] = date('Y-m-d H:i:s');
            $queue[$index]['synced_status'] = 'failed';
            Queue::save($queue);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Item not found']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Unknown action']);
}
