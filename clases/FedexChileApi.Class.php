<?php
/**
 * FedEx Chile Domestic API Client
 *
 * - OAuth2 client_credentials (Basic + multipart/form-data)
 * - createShipment / cancelShipment
 * - Soporta labelType "ONLY_DATA" (según instrucción complementaria)
 */

class FedExChileApi
{
    private string $oauthUrl;
    private string $createShipmentUrl;
    private string $cancelShipmentUrl;

    private string $clientId;
    private string $clientSecret;

    private ?string $accessToken = null;
    private int $accessTokenExpiresAt = 0;

    private int $timeoutSeconds = 300;
    private int $connectTimeoutSeconds = 10;
    private int $lowSpeedLimit = 1; // bytes
    private int $lowSpeedTime = 300;  // seconds

    public function __construct(string $clientId, string $clientSecret, array $options = [])
    {
        $this->oauthUrl          = $options['oauthUrl'];
        $this->createShipmentUrl = $options['createShipmentUrl'];
        $this->cancelShipmentUrl = $options['cancelShipmentUrl'];

        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;

        $this->timeoutSeconds         = (int)($options['timeout'] ?? $this->timeoutSeconds);
        $this->connectTimeoutSeconds  = (int)($options['connect_timeout'] ?? $this->connectTimeoutSeconds);
    }

    private function getAccessToken(): string
    {
        $now = time();
        if ($this->accessToken && $now < $this->accessTokenExpiresAt - 30) {
            return $this->accessToken;
        }

        $ch = curl_init($this->oauthUrl);
        $basic = base64_encode($this->clientId . ':' . $this->clientSecret);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . $basic,
                'Content-Type: multipart/form-data',
            ],
            CURLOPT_POSTFIELDS     => ['grant_type' => 'client_credentials'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Error cURL OAuth: ' . $err);
        }
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($raw, true);
        if ($http >= 400 || !is_array($data) || !isset($data['access_token'], $data['expires_in'])) {
            throw new RuntimeException('HTTP ' . $http . ' OAuth response: ' . $raw);
        }

        $this->accessToken = $data['access_token'];
        $this->accessTokenExpiresAt = time() + max(1, (int)$data['expires_in']);

        return $this->accessToken;
    }

    public function createShipment(array $payload): array
    {
        if (isset($payload['shipDate']) && !$this->isValidUsDate($payload['shipDate'])) {
            throw new InvalidArgumentException('shipDate debe tener formato MM/dd/yyyy');
        }

        // Soporte "ONLY_DATA": lo dejamos pasar tal cual — backend responderá buffer vacío en master/bultos.
        if (isset($payload['labelType']) && !in_array($payload['labelType'], ['ZPL', 'PNG', 'ONLY_DATA'], true)) {
            throw new InvalidArgumentException('labelType inválido (use ZPL, PNG u ONLY_DATA)');
        }

        $token = $this->getAccessToken();
        $ch = curl_init($this->createShipmentUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS      => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_TIMEOUT         => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT  => $this->connectTimeoutSeconds,
            CURLOPT_LOW_SPEED_LIMIT => $this->lowSpeedLimit,
            CURLOPT_LOW_SPEED_TIME  => $this->lowSpeedTime,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Error cURL createShipment: ' . $err);
        }
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($raw, true);
        if ($http >= 400 || $data === null) {
            throw new RuntimeException('HTTP ' . $http . ' createShipment response: ' . $raw);
        }
        return $data;
    }

    public function cancelShipment(string $masterTrackingNumber, array $credential = []): array
    {
        $token = $this->getAccessToken();
        $body = [
            // En PROD es requerido; en TEST puede omitirse, pero aquí permitimos inyectarlo.
            'credential' => (object)$credential,
            'masterTrackingNumber' => $masterTrackingNumber,
        ];

        $ch = curl_init($this->cancelShipmentUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Error cURL cancelShipment: ' . $err);
        }
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($raw, true);
        if ($http >= 400 || $data === null) {
            throw new RuntimeException('HTTP ' . $http . ' cancelShipment response: ' . $raw);
        }
        return $data;
    }

    private function isValidUsDate(string $date): bool
    {
        $p = explode('-', $date);
        if (count($p) !== 3) return false;
        [$dd, $mm, $yyyy] = $p;
        if (strlen($mm) !== 2 || strlen($dd) !== 2 || strlen($yyyy) !== 4) return false;
        return checkdate((int)$mm, (int)$dd, (int)$yyyy);
    }
}