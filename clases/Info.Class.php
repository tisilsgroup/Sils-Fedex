<?php
require_once('DataManager.Class.php');

class Info {

    public $conn;

    function __construct($c) {

        if(isset($c)){
                $this->conn = $c;
        } else {
                $this->conn = DataManager::getInstance();
        }
    }


    /// Recuperar configuración de FedEx
    /// Retorna un array con la configuración
    /// @return array
	public function recoverConfiguration() {
        $stmt = $this->conn->prepare("{CALL fedex.SP_configuracion_informacion ()}");
		$stmt->execute();

        return $stmt->fetch();
	}

    /// Recuperar envíos pendientes de impresión
    /// Retorna un array con los envíos pendientes
    /// @return array
    public function recoverPending() {
        $stmt = $this->conn->prepare("{CALL fedex.SP_solicitar_etiquetas ()}");
		$stmt->execute();

        return $stmt->fetchAll();
	}

    /// Guardar la respuesta del envío maestro
    /// Debe llamarse por cada respuesta maestra (masterTrackingNumber)
    /// Retornar el ID insertado
    /// @return array
    public function saveMaster(
        $accountNumber, $meterNumber, $wskeyUserCredential, $wspasswordUserCredential,
        $ctacli, $coduni, $codproc, $anio, $idSap, $punent, $sku, $postalCode,        
        $payloads, $masterTrackingNumber, $comments, $status
    ) {


        $stmt = $this->conn->prepare("{CALL fedex.SP_reportMaster (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}");
		$stmt->execute(array( $accountNumber, $meterNumber, $wskeyUserCredential, $wspasswordUserCredential,
                            $ctacli, $coduni, $codproc, $anio, $idSap, $punent, $sku, $postalCode,        
                            $payloads, $masterTrackingNumber, $comments, $status
                        ));

        return $stmt->fetch();
	}

    /// Guardar Información de cada bulto (detalle)
    /// Debe llamarse por cada secuencia (packageSequenceNumber, trackingNumber)
    /// Recibe el ID del master insertado
    /// @return array
    function saveDetail(
        $masterId, $masterTrackingNumber, $packageSequenceNumber, $trackingNumber, $contentType, $copiesToPrint, $labelType, $barcode1D, $barcode2D, $locationId, $ursaPrefix, $ursaSufix
    ) {

        $stmt = $this->conn->prepare("{CALL fedex.SP_reportDetail (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}");
        $stmt->execute(array( $masterId, $masterTrackingNumber, $packageSequenceNumber, $trackingNumber, $contentType, $copiesToPrint, $labelType, $barcode1D, $barcode2D, $locationId, $ursaPrefix, $ursaSufix ));
        
        return $stmt->fetch();
    }

    /// Guardar Información del Documento de Retorno (ZPL)
    /// Debe llamarse por cada respuesta maestra (masterTrackingNumber)
    /// Recibe el ID del master insertado
    /// @return array
    function saveDocResponseZPL(
        $masterId, $docResponseMasterTrackingNumber, $bufferBase64, $barcode1D, $barcode2D, $locationId, $ursaPrefix, $ursaSufix
    ) {

        $stmt = $this->conn->prepare("{CALL fedex.SP_reportDocResponseZPL (?, ?, ?, ?, ?, ?, ?, ?)}");
        $stmt->execute(array( $masterId, $docResponseMasterTrackingNumber, $bufferBase64, $barcode1D, $barcode2D, $locationId, $ursaPrefix, $ursaSufix ));

        return $stmt->fetch();
    }

    function Close() {

        $this->conn = null;

    }
}
?>