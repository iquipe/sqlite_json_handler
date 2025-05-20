<?php
namespace App\Http;

class Request
{
    private array $data;

    public function __construct()
    {
        $input = file_get_contents('php://input');
        $decodedData = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE && !empty($input)) {
            throw new \InvalidArgumentException('Invalid JSON input: ' . json_last_error_msg());
        }

        $this->data = is_array($decodedData) ? $decodedData : [];

        // Fallback to POST if no JSON body (e.g., for simple form posts or testing)
        if (empty($this->data) && !empty($_POST)) {
            $this->data = $_POST;
        }
    }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->data;
    }
}