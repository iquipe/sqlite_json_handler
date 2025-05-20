<?php
namespace App\Http;

class Response
{
    public static function json(array $data, int $statusCode = 200, array $headers = [])
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        foreach ($headers as $key => $value) {
            header("$key: $value");
        }
        echo json_encode($data);
        exit;
    }

    public static function success($data = null, string $message = 'Operation successful', int $statusCode = 200)
    {
        self::json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    public static function error(string $message, $errors = null, int $statusCode = 400)
    {
        $responseData = [
            'status' => 'error',
            'message' => $message,
        ];
        if ($errors !== null) {
            $responseData['errors'] = $errors;
        }
        self::json($responseData, $statusCode);
    }
}