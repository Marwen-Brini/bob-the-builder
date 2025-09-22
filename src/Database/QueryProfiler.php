<?php

declare(strict_types=1);

namespace Bob\Database;

class QueryProfiler
{
    protected array $profiles = [];

    protected bool $enabled = false;

    protected int $slowQueryThreshold = 1000; // milliseconds

    protected array $slowQueries = [];

    protected array $statistics = [
        'total_queries' => 0,
        'total_time' => 0,
        'select_count' => 0,
        'insert_count' => 0,
        'update_count' => 0,
        'delete_count' => 0,
    ];

    public function start(string $query, array $bindings = []): string
    {
        if (! $this->enabled) {
            return '';
        }

        $id = uniqid('query_', true);

        $this->profiles[$id] = [
            'query' => $query,
            'bindings' => $bindings,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(),
            'type' => $this->getQueryType($query),
        ];

        return $id;
    }

    public function end(string $id): void
    {
        if (! $this->enabled || ! isset($this->profiles[$id])) {
            return;
        }

        $profile = &$this->profiles[$id];
        $profile['end_time'] = microtime(true);
        $profile['end_memory'] = memory_get_usage();
        $profile['duration'] = ($profile['end_time'] - $profile['start_time']) * 1000; // Convert to ms
        $profile['memory_used'] = $profile['end_memory'] - $profile['start_memory'];

        // Update statistics
        $this->statistics['total_queries']++;
        $this->statistics['total_time'] += $profile['duration'];

        $type = $profile['type'];
        if (isset($this->statistics[$type.'_count'])) {
            $this->statistics[$type.'_count']++;
        }

        // Track slow queries
        if ($profile['duration'] > $this->slowQueryThreshold) {
            $this->slowQueries[] = [
                'query' => $profile['query'],
                'bindings' => $profile['bindings'],
                'duration' => $profile['duration'],
                'time' => date('Y-m-d H:i:s'),
            ];
        }
    }

    protected function getQueryType(string $query): string
    {
        $query = strtolower(trim($query));

        if (str_starts_with($query, 'select')) {
            return 'select';
        } elseif (str_starts_with($query, 'insert')) {
            // @codeCoverageIgnoreStart
            return 'insert';
            // @codeCoverageIgnoreEnd
        } elseif (str_starts_with($query, 'update')) {
            return 'update';
        } elseif (str_starts_with($query, 'delete')) {
            return 'delete';
        }

        return 'other';
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getProfiles(): array
    {
        return $this->profiles;
    }

    public function getSlowQueries(): array
    {
        return $this->slowQueries;
    }

    public function getStatistics(): array
    {
        $stats = $this->statistics;

        if ($stats['total_queries'] > 0) {
            $stats['average_time'] = $stats['total_time'] / $stats['total_queries'];
        } else {
            $stats['average_time'] = 0;
        }

        return $stats;
    }

    public function reset(): void
    {
        $this->profiles = [];
        $this->slowQueries = [];
        $this->statistics = [
            'total_queries' => 0,
            'total_time' => 0,
            'select_count' => 0,
            'insert_count' => 0,
            'update_count' => 0,
            'delete_count' => 0,
        ];
    }

    public function setSlowQueryThreshold(int $milliseconds): void
    {
        $this->slowQueryThreshold = max(1, $milliseconds);
    }

    public function getReport(): array
    {
        $stats = $this->getStatistics();

        return [
            'enabled' => $this->enabled,
            'total_queries' => $stats['total_queries'],
            'total_time_ms' => round($stats['total_time'], 2),
            'average_time_ms' => round($stats['average_time'], 2),
            'query_types' => [
                'select' => $stats['select_count'],
                'insert' => $stats['insert_count'],
                'update' => $stats['update_count'],
                'delete' => $stats['delete_count'],
            ],
            'slow_queries' => count($this->slowQueries),
            'slow_query_threshold_ms' => $this->slowQueryThreshold,
            'memory_peak' => memory_get_peak_usage(true),
        ];
    }

    public function getSlowestQueries(int $limit = 10): array
    {
        $queries = $this->slowQueries;
        usort($queries, fn ($a, $b) => $b['duration'] <=> $a['duration']);

        return array_slice($queries, 0, $limit);
    }
}
