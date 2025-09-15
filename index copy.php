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
            'postalCode' => '8240', // '8240000', //'8260343',
            'countryCode' => 'CL',
            'residential' => false,
            'streetLine1' => 'Las Acacias 7800',
            'streetLine2' => '',
            'streetLine3' => ''
        ],
    ],
    "shipDate" => "08-08-2025",
    "serviceType" => "FEDEX_PRIORITY",
    "packagingType" => "YOUR_PACKAGING",
    "shippingChargesPayment" => [
        "paymentType" => $paymentType ?? "SENDER",
        "accountNumber" => $accountNumber ?? "615612898" // BD
    ],
    'labelType' => 'ONLY_DATA',
    "requestedPackageLineItems" => [ // BD - SON LOS BULTOS A SER ENVIADOS
        [
            "itemDescription" => "82850194-89994- 1/1", // optional
            "weight" => [ // required
                "value" => 1, // required
                "units" => "KG" // required
            ],
            "dimensions" => [ // optional
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
        "specialServiceTypes" => [$specialServiceTypes] ?? ["PSDR"],
        "documentsToReturn" => [
        ],
        "customerDocsReference" => $customerDocsReference ?? "510100027"
    ],
    "clearanceDetail" => [
        "documentContent" => "NON_DOCUMENT"
    ],
    "insuranceValue" => [
        "amount" => 1146264,
        "currency" => "CHP"
    ]
];

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