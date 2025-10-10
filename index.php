<?php
/**
 * FedEx Chile Domestic API Client
 *
 * - OAuth2 client_credentials (Basic + multipart/form-data)
 * - createShipment / cancelShipment
 */
require_once($_SERVER['DOCUMENT_ROOT'].'/clases/FedexChileApi.Class.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/clases/Info.Class.php');

$fedexObj = new Info(null);
$fedexCnf = $fedexObj->recoverConfiguration();
$fedexInf = $fedexObj->recoverPending();
$fedexObj->Close();

// print_r($fedexInf);

$TEST_USERNAME = $fedexCnf['conf_texto_1'];
$TEST_PASSWORD = $fedexCnf['conf_texto_2'];

$paymentType   = $fedexCnf['conf_texto_23'];
$accountNumber = $fedexCnf['conf_texto_24'];

$specialServiceTypes   = $fedexCnf['conf_texto_25'];
$customerDocsReference = $fedexCnf['conf_texto_26'];

$accountNumber              = $fedexCnf['conf_texto_3'];
$meterNumber                = $fedexCnf['conf_texto_4'];
$wskeyUserCredential        = $fedexCnf['conf_texto_5'];
$wspasswordUserCredential   = $fedexCnf['conf_texto_6'];

// Credenciales de envío
$CREDENTIAL = [
    "accountNumber"            => $accountNumber,
    "meterNumber"              => $meterNumber,
    "wskeyUserCredential"      => $wskeyUserCredential,
    "wspasswordUserCredential" => $wspasswordUserCredential
];

$OPTIONS = [
    "oauthUrl"            => $fedexCnf['conf_texto_7'],
    "createShipmentUrl"   => $fedexCnf['conf_texto_8'],
    "cancelShipmentUrl"   => $fedexCnf['conf_texto_9']
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
    $streetMaxLength = 35; // 3 líneas de 35 caracteres cada una
    $street          = strOrEmpty($row['destinatarioDireccion'] ?? '');
    $streetLine1     = '';
    $streetLine2     = '';
    $streetLine3     = '';

    $streetActualLength = strlen($street);
    if ($streetActualLength > $streetMaxLength) {
        $streetLine1 = substr($street, 0, $streetMaxLength);
        $street      = substr($street, $streetMaxLength, $streetActualLength - $streetMaxLength);
        $streetLine2 = $street;
    } else {
        $streetLine1 = $street;
        $street      = '';
    }

    $streetActualLength = strlen($street);
    if ($streetActualLength > $streetMaxLength) {
        $streetLine2 = substr($street, 0, $streetMaxLength);
        $street      = substr($street, $streetMaxLength, $streetActualLength - $streetMaxLength);
        $streetLine3 = $street;
    }

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
            'streetLine1'         => $streetLine1,
            'streetLine2'         => $streetLine2,
            'streetLine3'         => $streetLine3
        ],
    ];

    $bultos      = (int)($row['paqueteBultos'] ?? 1);
    $pesoTotalKg = (float)($row['paquetePeso'] ?? 0);
    $pesoPorBulto = $bultos > 0 ? $pesoTotalKg / $bultos : $pesoTotalKg;

    $largo = (float)($row['paqueteLargo'] ?? 0);
    $ancho = (float)($row['paqueteAncho'] ?? 0);
    $alto  = (float)($row['paqueteAlto']  ?? 0);

    $idSap  = (int)($row['id_sap']  ?? 0);
    $anio   = substr(strOrEmpty($row['anio'] ?? ''), -2);
    $punEnt = strOrEmpty($row['punent'] ?? '');
    $docId  = $idSap.$anio.$punEnt;

    $requestedPackageLineItems = [];
    for ($i = 1; $i <= max(1, $bultos); $i++) {
        $requestedPackageLineItems[] = [
            "itemDescription" => sprintf(
                "%s-%s- %d/%d",
                $idSap,
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
                'personName'  => $fedexCnf['conf_texto_10'],
                'phoneNumber' => $fedexCnf['conf_texto_11'],
                'companyName' => $fedexCnf['conf_texto_12'],
                'email'       => $fedexCnf['conf_texto_13'],
                'vatNumber'   => $fedexCnf['conf_texto_14'],
            ],
            'address' => [
                'city'                => $fedexCnf['conf_texto_15'],
                'stateOrProvinceCode' => $fedexCnf['conf_texto_16'],
                'postalCode'          => $fedexCnf['conf_texto_17'],
                'countryCode'         => $fedexCnf['conf_texto_18'],
                'residential'         => false,
                'streetLine1'         => $fedexCnf['conf_texto_20'],
                'streetLine2'         => $fedexCnf['conf_texto_21'],
                'streetLine3'         => $fedexCnf['conf_texto_22'],
            ],
        ],
        'recipient' => $recipient,
        "shipDate" => date('d-m-Y'),
        "serviceType" => "FEDEX_PRIORITY",
        "packagingType" => "YOUR_PACKAGING",
        "shippingChargesPayment" => [
            "paymentType"   => $paymentType   ?? "SENDER",
            "accountNumber" => $accountNumber
        ],
        'labelType' => 'ONLY_DATA',
        "requestedPackageLineItems" => $requestedPackageLineItems,
        "specialServicesRequested" => [
            "specialServiceTypes"   => isset($specialServiceTypes) ? [$specialServiceTypes] : ["specialServiceTypes"],
            "documentsToReturn"     => [[
                "docType" => "DELR",
                "docId"   => $docId
            ]],
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

    $respuesta = generarEnvio($payload);
    guardarPayload( $row, $payload, $respuesta );
}


function generarEnvio( $payload ) {
    global $client;
    $responses = [];
    // foreach ($payloads as $payload) {   
        try {
            $res = $client->createShipment($payload);
            $responses[] = $res;
            echo "<br><br>Envío creado OK. Master Tracking Number: " . ($res['masterTrackingNumber'] ?? 'N/A') . "</br></br>";
        } catch (Throwable $e) {
            echo "<br><br>Error creando envío: " . $e->getMessage() . "</br>";
        }
    // }

    return $responses;
}

function guardarPayload( $row, $payload, $responseMaster) {
    global $accountNumber, $meterNumber, $wskeyUserCredential, $wspasswordUserCredential;

    // Datos adicionales para guardar
    $ctacli     = strOrEmpty($row['ctacli'] ?? '');
    $coduni     = strOrEmpty($row['coduni'] ?? '');
    $codproc    = strOrEmpty($row['codproc'] ?? '');
    $anio       = strOrEmpty($row['anio'] ?? '');
    $idSap      = strOrEmpty($row['id_sap'] ?? '');
    $punent     = strOrEmpty($row['punent'] ?? '');
    $sku        = strOrEmpty($row['skusap'] ?? '');
    $postalCode = strOrEmpty($row['postalCode'] ?? '');

    echo '<br><br><br>';    
    foreach ($responseMaster as $response) {
        
        // ======================
        // MOSTRAR  Y GUARDAR DATOS
        // ======================
        $fedexRespMasterId = 0;
        $fedexObj          = new Info(null);

        // ======================
        // DATOS MAESTROS
        // ======================

        $masterTrackingNumber = $response['masterTrackingNumber'] ?? null;
        $comments             = $response['comments'] ?? null;
        $status               = $response['status'] ?? null;

        echo "=== MAESTRO ===</br>";
        echo "Master Tracking: $masterTrackingNumber</br>";
        echo "Comments: $comments</br>";
        echo "Status: $status</br></br>";
        
        $fedexRespMaster = $fedexObj->saveMaster( $accountNumber, $meterNumber, $wskeyUserCredential, $wspasswordUserCredential,
                            $ctacli, $coduni, $codproc, $anio, $idSap, $punent, $sku, $postalCode,       
                            $payload, $masterTrackingNumber, $comments, $status );
        
        if( !empty($fedexRespMaster) && isset($fedexRespMaster['resultado']) && $fedexRespMaster['resultado'] == 1 && isset($fedexRespMaster['masterId']) ) {
            $fedexRespMasterId = $fedexRespMaster['masterId'] ?? 0;
            echo "Master guardado con ID: $fedexRespMasterId</br></br>";
        } 
        
        // if ($fedexRespMasterId > 0)  {
        //     foreach ($docResponseZPL as $doc) {
        //         echo "DocResponse ZPL:</br>";
        //         print_r($doc);
        //         echo '<br><br><br>';
        //     }

        //     echo "</br>=== DETALLE ===</br>";
        //     foreach ($detalle as $d) {
        //         print_r($d);
        //         echo '<br><br><br>';
        //     }
        // }
        // ======================
        // DETALLE (bultos)
        // ======================
        $detalle = [];
        if (isset($response['labelResponse'])) {
            foreach ($response['labelResponse'] as $label) {
                $master = $label['masterTrackingNumber'] ?? $masterTrackingNumber;
                foreach ($label['contentResponse'] as $content) {
                    $detalle[] = [
                        'masterTrackingNumber'  => $master,
                        'packageSequenceNumber' => $label['packageSequenceNumber'] ?? null,
                        'trackingNumber'        => $label['trackingNumber'] ?? null,
                        'contentType'           => $content['contentType'] ?? null,
                        'copiesToPrint'         => $content['copiesToPrint'] ?? null,
                        'labelType'             => $content['labelType'] ?? null,
                        'barcode1D'             => $content['barcode1D'] ?? null,
                        'barcode2D'             => $content['barcode2D'] ?? null,
                        'locationId'            => $content['locationId'] ?? null,
                        'ursaPrefix'            => $content['ursaPrefix'] ?? null,
                        'ursaSufix'             => $content['ursaSufix'] ?? null
                    ];

                    $fedexRespDetail = $fedexObj->saveDetail(
                        $fedexRespMasterId,
                        $master,
                        $label['packageSequenceNumber'] ?? null,
                        $label['trackingNumber'] ?? null,
                        $content['contentType'] ?? null,
                        $content['copiesToPrint'] ?? null,
                        $content['labelType'] ?? null,
                        $content['barcode1D'] ?? null,
                        $content['barcode2D'] ?? null,
                        $content['locationId'] ?? null,
                        $content['ursaPrefix'] ?? null,
                        $content['ursaSufix'] ?? null
                    );
                }
            }
        }

        // Filtramos docResponse SOLO si el labelType es ZPL
        $docResponseZPL = [];
        if (isset($response['docResponse'])) {
            foreach ($response['docResponse'] as $doc) {
                foreach ($doc['contentResponse'] as $content) {
                    if (($content['labelType'] ?? '') === 'ZPL') {
                        $docResponseZPL[] = [
                            'bufferBase64' => $content['bufferBase64'] ?? null,
                            'barcode1D'    => $content['barcode1D'] ?? null,
                            'barcode2D'    => $content['barcode2D'] ?? null,
                            'locationId'   => $content['locationId'] ?? null,
                            'ursaPrefix'   => $content['ursaPrefix'] ?? null,
                            'ursaSufix'    => $content['ursaSufix'] ?? null
                        ];

                        $fedexRespDocResponseZPL = $fedexObj->saveDocResponseZPL(
                            $fedexRespMasterId,
                            $content['bufferBase64'] ?? null,
                            $content['barcode1D'] ?? null,
                            $content['barcode2D'] ?? null,
                            $content['locationId'] ?? null,
                            $content['ursaPrefix'] ?? null,
                            $content['ursaSufix'] ?? null
                        );
                    }
                }
            }
        }

        
        
        $fedexObj->Close();

        
    }
}

/*
// === Cancelación de envío (con la guía máster que recibiste) ===
$masterTrackingNumber = 'REEMPLAZA_CON_TU_GUIA';
try {
    $cancel = $client->cancelShipment($masterTrackingNumber, $CREDENTIAL);
    echo "Cancelación OK</br>";
    print_r($cancel);
} catch (Throwable $e) {
    echo "Error cancelando envío: " . $e->getMessage() . "</br>";
}
*/