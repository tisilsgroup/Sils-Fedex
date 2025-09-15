<?php

/**
 * FedEx Chile Domestic API Client
 *
 * - OAuth2 client_credentials (Basic + multipart/form-data)
 * - createShipment / cancelShipment
 */

$TEST_USERNAME = 'oY3jhNDORE62o2zPnwnW';
$TEST_PASSWORD = 'l6uCn7Ll96lyot4T6aqQ3VLlq';

// Tus credenciales de envío (para el objeto credential)
$CREDENTIAL = [
    "accountNumber"            => "615612898",
    "meterNumber"              => "256549685",
    "wskeyUserCredential"      => "ICA5VOTfC6F74Dlt",
    "wspasswordUserCredential" => "5woctSZ7SjTeV7gBPVPgHZklu"
];

$OPTIONS = [
    "oauthUrl"            => "https://wsbeta.fedex.com/LAC/ServicesAPI/oauth/token",
    "createShipmentUrl"   => "https://wsbeta.fedex.com/LAC/ServicesAPI/cdrm/api/createShipment",
    "cancelShipmentUrl"   => "https://wsbeta.fedex.com/LAC/ServicesAPI/cdrm/api/cancelShipment"
];



$client = new FedExChileApi($TEST_USERNAME, $TEST_PASSWORD, $OPTIONS);

$payload = [
    "credential" => $CREDENTIAL,
    'shipper' => [ // BD
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
            'postalCode' => '9020',// '9020000', // '9061529',
            'countryCode' => 'CL',
            'residential' => false,
            'streetLine1' => 'Puerto Santiago 259',
            'streetLine2' => '',
            'streetLine3' => ''
        ],
    ],
    'recipient' => [ // BD
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
        "paymentType" => "SENDER",
        "accountNumber" => "615612898" // BD
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
        "specialServiceTypes" => ["PSDR"],
        "documentsToReturn" => [
        ],
        "customerDocsReference" => "510100027"
    ],
    "clearanceDetail" => [
        "documentContent" => "NON_DOCUMENT"//, // TIPO DE CONTENIDO, ENVIOS INTRA CHILE PUEDEN SER "NON_DOCUMENT" O "DOCUMENT"
        // "commodities" => [
        //     [
        //         "description" => "some packs",
        //         "countryOfManufacture" => "CL",
        //         "numberOfPieces" => 1,
        //         "weight" => [
        //             "value" => 0.0,
        //             "units" => "KG"
        //         ],
        //         "quantity" => 0,
        //         "quantityUnits" => "PCS",
        //         "unitPrice" => [
        //             "amount" => 0.0,
        //             "currency" => "CHP"
        //         ]
        //     ]
        // ]
    ],
    // LAS REFERENCIAS SON CAMPOS LIBRES QUE SE PUEDEN UTILIZAR PARA DIFERENTES FINES COMO SEGUIMIENTO, IDENTIFICACIÓN, ETC.
    // "references" => [
    //     [
    //         "customerReferenceType" => "CUSTOMER_REFERENCE", // REFERENCIA CLIENTE
    //         "value" => "89994"
    //     ],
    //     [
    //         "customerReferenceType" => "PURCHACE_ORDER", // ORDEN DE COMPRA
    //         "value" => "82850194"
    //     ],
    //     [
    //         "customerReferenceType" => "INVOICE", // FACTURA
    //         "value" => "89994"
    //     ]
    // ],
    // VALOR DEL SEGURO, SI NO SE REQUIERE, DEJAR EN 0
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