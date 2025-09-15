<?php
require_once('DataManager.Class.php');

class Fedex {

    public $conn;

    function __construct($c) {

        if(isset($c)){
                $this->conn = $c;
        } else {
                $this->conn = DataManager::getInstance();
        }
    }


	public function recoverConfiguration() {
        $stmt = $this->conn->prepare("{CALL fedex.SP_configuracion_informacion ()}");
		$stmt->execute();

        return $stmt->fetch();
	}
	
    public function recoverPending() {
        $stmt = $this->conn->prepare("{CALL fedex.SP_fedex_solicitar_etiquetas ()}");
		$stmt->execute();

        return $stmt->fetchAll();
	}

    public function saveTransportOrderNumber(   $ctacli, $coduni, $codproc, $punent, $anio, $skusap, 
                                                $transportOrderNumber, $barcode, $destinationCoverageAreaName, $idSap, $pieza, $bulto ) {
        $folio = $transportOrderNumber;

        $stmt = $this->conn->prepare("{CALL fedex.SP_fedex_reportar (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)}");
		$stmt->execute(array( $ctacli, $coduni, $codproc, $punent, $anio, $skusap, $transportOrderNumber, $barcode, $destinationCoverageAreaName, $idSap, $pieza, $bulto, $folio ));

        return $stmt->fetch();
	}


    function Close() {

        $this->conn = null;

    }
}
?>