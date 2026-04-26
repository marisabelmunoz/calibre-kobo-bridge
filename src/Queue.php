<?php
declare(strict_types=1);

namespace CalibreOpds;

class Queue
{
    private static function getQueueFile(): string
    {
        return __DIR__ . '/../data/queue.json';
    }
    
    private static function ensureQueueFile(): void
    {
        $file = self::getQueueFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!file_exists($file)) {
            file_put_contents($file, json_encode([]));
        }
    }
    
    public static function load(): array
    {
        self::ensureQueueFile();
        $queue = json_decode(file_get_contents(self::getQueueFile()), true);
        return is_array($queue) ? $queue : [];
    }
    
    public static function save(array $queue): bool
    {
        return file_put_contents(self::getQueueFile(), json_encode($queue, JSON_PRETTY_PRINT)) !== false;
    }
    
    public static function count(): int
    {
        return count(self::load());
    }
    
    public static function add(array $item): bool
    {
        $queue = self::load();
        $item['added'] = date('Y-m-d H:i:s');
        $item['synced'] = null;
        $item['synced_status'] = 'pending';
        $queue[] = $item;
        return self::save($queue);
    }
    
    public static function getAll(): array
    {
        return self::load();
    }
    
    public static function remove(int $index): bool
    {
        $queue = self::load();
        if (isset($queue[$index])) {
            unset($queue[$index]);
            return self::save(array_values($queue));
        }
        return false;
    }
    
    public static function clear(): bool
    {
        return self::save([]);
    }
    
    public static function markSynced(int $index, bool $success = true): bool
    {
        $queue = self::load();
        if (isset($queue[$index])) {
            $queue[$index]['synced'] = date('Y-m-d H:i:s');
            $queue[$index]['synced_status'] = $success ? 'success' : 'failed';
            return self::save($queue);
        }
        return false;
    }
}