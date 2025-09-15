<?php
/**
 * FedEx Chile Domestic API Client
 *
 * - OAuth2 client_credentials (Basic + multipart/form-data)
 * - createShipment / cancelShipment
 */
require_once($_SERVER['DOCUMENT_ROOT'].'/clases/FedexChileApi.Class.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/clases/Fedex.Class.php');

$fedexObj = new Fedex(null);
$fedexCnf = $fedexObj->recoverConfiguration();
$fedexInf = $fedexObj->recoverPending();
$fedexObj->Close();

print_r($fedexInf);

$TEST_USERNAME = $fedexCnf['conf_texto_150'];
$TEST_PASSWORD = $fedexCnf['conf_texto_151'];

$paymentType   = $fedexCnf['conf_texto_172'];
$accountNumber = $fedexCnf['conf_texto_173'];

$specialServiceTypes   = $fedexCnf['conf_texto_174'];
$customerDocsReference = $fedexCnf['conf_texto_175'];

// Credenciales de envío
$CREDENTIAL = [
    "accountNumber"            => $fedexCnf['conf_texto_152'],
    "meterNumber"              => $fedexCnf['conf_texto_153'],
    "wskeyUserCredential"      => $fedexCnf['conf_texto_154'],
    "wspasswordUserCredential" => $fedexCnf['conf_texto_155']
];

$OPTIONS = [
    "oauthUrl"            => $fedexCnf['conf_texto_156'],
    "createShipmentUrl"   => $fedexCnf['conf_texto_157'],
    "cancelShipmentUrl"   => $fedexCnf['conf_texto_158']
];

function onlyDigitsAndPlus($s) {
    return preg_replace('/[^0-9+]/', '', (string)$s);
}
function strOrEmpty($v) {
    return isset($v) ? (string)$v : '';
}



$client = new FedExChileApi($TEST_USERNAME, $TEST_PASSWORD, $OPTIONS);

$payloads = [];

foreach ($fedexInf as $row) {
    $recipient = [
        'contact' => [
            'personName'  => strOrEmpty($row['destinatarioContacto'] ?? $row['destinatarioNombre']),
            'phoneNumber' => onlyDigitsAndPlus($row['destinatarioTelefono'] ?? ''),
            'companyName' => strOrEmpty($row['destinatarioNombre'] ?? ''),
            'email'       => strOrEmpty($row['destinatarioEmail'] ?? ''),
            'vatNumber'   => strOrEmpty($row['ctacli'] ?? ''),
        ],
        'address' => [
            'city'                => strOrEmpty($row['comuna'] ?? ''),
            'stateOrProvinceCode' => strOrEmpty($row['codreg'] ?? $row['region'] ?? ''),
            'postalCode'          => strOrEmpty($row['postalCode'] ?? '8240000'),
            'countryCode'         => 'CL',
            'residential'         => false,
            'streetLine1'         => strOrEmpty($row['destinatarioDireccion'] ?? ''),
            'streetLine2'         => '',
            'streetLine3'         => ''
        ],
    ];

    $bultos      = (int)($row['paqueteBultos'] ?? 1);
    $pesoTotalKg = (float)($row['paquetePeso'] ?? 0);
    $pesoPorBulto = $bultos > 0 ? $pesoTotalKg / $bultos : $pesoTotalKg;

    $largo = (float)($row['paqueteLargo'] ?? 0);
    $ancho = (float)($row['paqueteAncho'] ?? 0);
    $alto  = (float)($row['paqueteAlto']  ?? 0);

    $requestedPackageLineItems = [];
    for ($i = 1; $i <= max(1, $bultos); $i++) {
        $requestedPackageLineItems[] = [
            "itemDescription" => sprintf(
                "%s-%s- %d/%d",
                strOrEmpty($row['id_sap'] ?? ''),
                strOrEmpty($row['paqueteCodigo'] ?? ''),
                $i,
                max(1, $bultos)
            ),
            "weight" => [
                "value" => (float)number_format($pesoPorBulto, 2, '.', ''),
                "units" => "KG"
            ],
            "dimensions" => [
                "length" => (float)number_format($largo, 2, '.', ''),
                "width"  => (float)number_format($ancho, 2, '.', ''),
                "height" => (float)number_format($alto,  2, '.', ''),
                "units"  => "CM"
            ]
        ];
    }

    $payload = [
        "credential" => $CREDENTIAL,
        'shipper' => [
            'contact' => [
                'personName'  => $fedexCnf['conf_texto_159'],
                'phoneNumber' => $fedexCnf['conf_texto_160'],
                'companyName' => $fedexCnf['conf_texto_161'],
                'email'       => $fedexCnf['conf_texto_162'],
                'vatNumber'   => $fedexCnf['conf_texto_163'],
            ],
            'address' => [
                'city'                => $fedexCnf['conf_texto_164'],
                'stateOrProvinceCode' => $fedexCnf['conf_texto_165'],
                'postalCode'          => $fedexCnf['conf_texto_166'],
                'countryCode'         => $fedexCnf['conf_texto_167'],
                'residential'         => false,
                'streetLine1'         => $fedexCnf['conf_texto_169'],
                'streetLine2'         => $fedexCnf['conf_texto_170'],
                'streetLine3'         => $fedexCnf['conf_texto_171'],
            ],
        ],
        'recipient' => $recipient,
        "shipDate" => date('d-m-Y'),
        "serviceType" => "FEDEX_PRIORITY",
        "packagingType" => "YOUR_PACKAGING",
        "shippingChargesPayment" => [
            "paymentType"   => $paymentType   ?? "SENDER",
            "accountNumber" => $accountNumber ?? "615612898"
        ],
        'labelType' => 'ONLY_DATA',
        "requestedPackageLineItems" => $requestedPackageLineItems,
        "specialServicesRequested" => [
            "specialServiceTypes"   => isset($specialServiceTypes) ? [$specialServiceTypes] : ["PSDR"],
            "documentsToReturn"     => [],
            "customerDocsReference" => $customerDocsReference ?? "510100027"
        ],
        "clearanceDetail" => [
            "documentContent" => "NON_DOCUMENT"
        ],
        "insuranceValue" => [
            "amount"   => 1146264,
            "currency" => "CHP"
        ]
    ];

    $payload['_meta'] = [
        'punent' => strOrEmpty($row['punent'] ?? ''),
        'anio'   => strOrEmpty($row['anio'] ?? ''),
        'sku'    => strOrEmpty($row['skusap'] ?? ''),
        'desc'   => strOrEmpty($row['paqueteDescripcion'] ?? ''),
    ];

    $payloads[] = $payload;
}

try {
    $res = $client->createShipment($payload);
    
    $resJson = json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    echo "<br><br>Respuesta JSON:<br><pre>" . htmlspecialchars($resJson) . "</pre>";
    // echo "Respuesta\n\n\n\n";
    // print_r($res);
    // echo "Envío creado OK\n\n\n\n";

    // === Ejemplo de lectura de datos esperados (nombres de campos pueden variar según backend) ===
    $master = $res['masterTrackingNumber'] ?? $res['master']['trackingNumber'] ?? null; // Guía máster / Guía de bulto
    $pouch  = $res['pouchId'] ?? $res['pouch']['id'] ?? null;                           // Pouch ID
    // Etiquetas de retorno de documentos (cuando labelType=ONLY_DATA, estas sí deberían venir)
    $returnTag   = $res['returnDocuments']['returnTag']   ?? null; // ej. base64/ZPL/PNG según implementación
    $returnLabel = $res['returnDocuments']['returnLabel'] ?? null;

    // echo "Master/Guía de bulto: " . ($master ?: 'N/D') . PHP_EOL;
    // echo "Pouch ID: " . ($pouch ?: 'N/D') . PHP_EOL;
    // echo "Return TAG presente: " . (empty($returnTag) ? 'No' : 'Sí') . PHP_EOL;
    // echo "Return LABEL DOCS presente: " . (empty($returnLabel) ? 'No' : 'Sí') . PHP_EOL;

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