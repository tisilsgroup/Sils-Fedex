<?php

/**
 * FedEx Chile Domestic API Client (TEST)
 * PHP 7.4+
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

    private string $clientId;     // USERNAME (test)
    private string $clientSecret; // PASSWORD (test)

    private ?string $accessToken = null;
    private int $accessTokenExpiresAt = 0;

    private int $timeoutSeconds = 25;
    private int $connectTimeoutSeconds = 10;

    public function __construct(string $clientId, string $clientSecret, array $options = [])
    {
        $this->oauthUrl          = $options['oauthUrl']          ?? 'https://wsbeta.fedex.com/LAC/ServicesAPI/oauth/token';
        $this->createShipmentUrl = $options['createShipmentUrl'] ?? 'https://wsbeta.fedex.com/LAC/ServicesAPI/cdrm/api/createShipment';
        $this->cancelShipmentUrl = $options['cancelShipmentUrl'] ?? 'https://wsbeta.fedex.com/LAC/ServicesAPI/cdrm/api/cancelShipment';

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
        // shipDate debe ser MM/dd/yyyy
        if (isset($payload['shipDate']) && !$this->isValidUsDate($payload['shipDate'])) {
            throw new InvalidArgumentException('shipDate debe tener formato MM/dd/yyyy');
        }

        // Soporte "ONLY_DATA": lo dejamos pasar tal cual — backend responderá buffer vacío en master/bultos.
        if (isset($payload['labelType']) && !in_array($payload['labelType'], ['ZPL', 'PNG', 'ONLY_DATA'], true)) {
            throw new InvalidArgumentException('labelType inválido (use ZPL, PNG u ONLY_DATA)');
        }

        $token = $this->getAccessToken();
        echo "Using Access Token: " . $token . "...\n";
        $ch = curl_init($this->createShipmentUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
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
        $p = explode('/', $date);
        if (count($p) !== 3) return false;
        [$mm, $dd, $yyyy] = $p;
        if (strlen($mm) !== 2 || strlen($dd) !== 2 || strlen($yyyy) !== 4) return false;
        return checkdate((int)$mm, (int)$dd, (int)$yyyy);
    }
}

// Credenciales OAuth (TEST) del doc
$TEST_USERNAME = 'oY3jhNDORE62o2zPnwnW';
$TEST_PASSWORD = 'l6uCn7Ll96lyot4T6aqQ3VLlq';

// Tus credenciales de envío (para el objeto credential)
$CREDENTIAL = [
    "accountNumber"            => "615612898",
    "meterNumber"              => "256549685",
    "wskeyUserCredential"      => "ICA5VOTfC6F74Dlt",
    "wspasswordUserCredential" => "5woctSZ7SjTeV7gBPVPgHZklu"
];

$client = new FedExChileApi($TEST_USERNAME, $TEST_PASSWORD);

// Payload con 1 bulto, payer SENDER 615612898 y labelType "ONLY_DATA"
$payload = [
    "credential" => $CREDENTIAL,
    'shipper' => [
        'contact' => [
            'personName'  => 'Sils Group',
            'phoneNumber' => '+56931987281',
            'companyName' => 'Sils Group',
            'email'       => 'contacto@sils.group',
            'vatNumber'   => '77057527-3',
        ],
        'address' => [
            'city' => 'Pudahuel',
            'stateOrProvinceCode' => 'CL',
            'postalCode' => '9020000', // '9061529',
            'countryCode' => 'CL',
            'residential' => false,
            'streetLine1' => 'Puerto Santiago 259',
            'streetLine2' => '',
            'streetLine3' => ''
        ],
    ],
    'recipient' => [
        'contact' => [
            'personName'  => 'Carlos Jordan',
            'phoneNumber' => '+56959495349',
            'companyName' => 'Amo mi Negocio',
            'email'       => 'contacto@amominegocio.cl',
            'vatNumber'   => '77639373-8',
        ],
        'address' => [
            'city' => 'La Florida',
            'stateOrProvinceCode' => 'CL',
            'postalCode' => '8240000', //'8260343',
            'countryCode' => 'CL',
            'residential' => false,
            'streetLine1' => 'Las Acacias 7800',
            'streetLine2' => '',
            'streetLine3' => ''
        ],
    ],
    "shipDate" => "08/28/2025",
    "serviceType" => "FEDEX_PRIORITY",
    "packagingType" => "YOUR_PACKAGING",
    "shippingChargesPayment" => [
        "paymentType" => "SENDER",
        "accountNumber" => "615612898"
    ],
    'labelType' => 'ONLY_DATA',
    "requestedPackageLineItems" => [
        [
            "itemDescription" => "82850194-89994- 1/1",
            "weight" => [
                "value" => 1,
                "units" => "KG"
            ],
            "dimensions" => [
                "length" => 1,
                "width" => 1,
                "height" => 1,
                "units" => "CM"
            ]
        ],
        [
            "itemDescription" => "82850222-90022- 1/1",
            "weight" => [
                "value" => 1,
                "units" => "KG"
            ],
            "dimensions" => [
                "length" => 1,
                "width" => 1,
                "height" => 1,
                "units" => "CM"
            ]
        ]
    ],
    "specialServicesRequested" => [
        "specialServiceTypes" => [
            "RETURN_DOCUMENTS" // TAG QUE INDICA QUE SE INCLUYE DOCUMENTACIÓN LA REFERENCIA DEL DOCUMENTO A RETORNAR
        ],
        "documentsToReturn" => [
            [
                "docType" => "CI",   // TIPO DE DOCUMENTO A RETORNAR
                "docId"   => "89994" // IDENTIFICADOR DEL DOCUMENTO A RETORNAR (REFERENCIA)
            ]
        ],
        "customerDocsReference" => "89994"
    ],
    "clearanceDetail" => [
        "documentContent" => "NON_DOCUMENT", // TIPO DE CONTENIDO, ENVIOS INTRA CHILE PUEDEN SER "NON_DOCUMENT" O "DOCUMENT"
        "commodities" => [
            [
                "description" => "some packs",
                "countryOfManufacture" => "CL",
                "numberOfPieces" => 1,
                "weight" => [
                    "value" => 0.0,
                    "units" => "KG"
                ],
                "quantity" => 0,
                "quantityUnits" => "PCS",
                "unitPrice" => [
                    "amount" => 0.0,
                    "currency" => "CHP"
                ]
            ]
        ]
    ],
    // LAS REFERENCIAS SON CAMPOS LIBRES QUE SE PUEDEN UTILIZAR PARA DIFERENTES FINES COMO SEGUIMIENTO, IDENTIFICACIÓN, ETC.
    "references" => [
        [
            "customerReferenceType" => "CUSTOMER_REFERENCE", // REFERENCIA CLIENTE
            "value" => "89994"
        ],
        [
            "customerReferenceType" => "PURCHACE_ORDER", // ORDEN DE COMPRA
            "value" => "82850194"
        ],
        [
            "customerReferenceType" => "INVOICE", // FACTURA
            "value" => "89994"
        ]
    ],
    // VALOR DEL SEGURO, SI NO SE REQUIERE, DEJAR EN 0
    "insuranceValue" => [
        "amount" => 1146264,
        "currency" => "CHP"
    ]
];

try {
    $res = $client->createShipment($payload);
    echo "Envío creado OK\n";
    // === Ejemplo de lectura de datos esperados (nombres de campos pueden variar según backend) ===
    $master = $res['masterTrackingNumber'] ?? $res['master']['trackingNumber'] ?? null; // Guía máster / Guía de bulto
    $pouch  = $res['pouchId'] ?? $res['pouch']['id'] ?? null;                           // Pouch ID
    // Etiquetas de retorno de documentos (cuando labelType=ONLY_DATA, estas sí deberían venir)
    $returnTag   = $res['returnDocuments']['returnTag']   ?? null; // ej. base64/ZPL/PNG según implementación
    $returnLabel = $res['returnDocuments']['returnLabel'] ?? null;

    echo "Master/Guía de bulto: " . ($master ?: 'N/D') . PHP_EOL;
    echo "Pouch ID: " . ($pouch ?: 'N/D') . PHP_EOL;
    echo "Return TAG presente: " . (empty($returnTag) ? 'No' : 'Sí') . PHP_EOL;
    echo "Return LABEL DOCS presente: " . (empty($returnLabel) ? 'No' : 'Sí') . PHP_EOL;

    // Si quisieras persistir etiquetas de retorno cuando existen:
    // if (!empty($returnTag))   file_put_contents('return_tag.bin', base64_decode($returnTag));
    // if (!empty($returnLabel)) file_put_contents('return_label.bin', base64_decode($returnLabel));

} catch (Throwable $e) {
    echo "<br><br>Error creando envío: " . $e->getMessage() . "\n";
}
/*
// === Cancelación de envío (con la guía máster que recibiste) ===
$masterTrackingNumber = 'REEMPLAZA_CON_TU_GUIA';
try {
    $cancel = $client->cancelShipment($masterTrackingNumber, $CREDENTIAL);
    echo "Cancelación OK\n";
    print_r($cancel);
} catch (Throwable $e) {
    echo "Error cancelando envío: " . $e->getMessage() . "\n";
}
*/