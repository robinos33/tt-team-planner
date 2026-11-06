<?php
declare(strict_types=1);

// Doublures minimales des classes REST de WordPress, absentes hors WordPress.

if (! class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        /** @param array<string, mixed> $params */
        public function __construct(private array $params = []) {}

        public function get_param(string $key): mixed
        {
            return $this->params[$key] ?? null;
        }
    }
}

if (! class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        public function __construct(public mixed $data = null, public int $status = 200) {}

        public function get_data(): mixed
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }
    }
}
