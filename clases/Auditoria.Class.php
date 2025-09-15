<?php
require_once('DataManager.Class.php');

class Auditoria {

	public $conn;

    function __construct($c) {
    	if(isset($c)) {
    		$this->conn = $c;
    	} else {
    		$this->conn = DataManager::getInstance();
    	}
    }

	public function agregaAuditoria( $accion, $tabla, $sql, $correcto = 1, $mensaje='' ) {
        $usuario     = 'Cron-Fedex';
		$url         = "http://".$_SERVER['HTTP_HOST'].":".$_SERVER['SERVER_PORT'].$_SERVER['REQUEST_URI'];
		$yourbrowser = '';
		$ipAddress   = $_SERVER['REMOTE_ADDR'];

		$stmt = $this->conn->prepare("{CALL chilexpress.SP_Auditoria_Ingresa(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )}");
		$stmt->execute(array(   $usuario, $accion, $tabla, $sql, $ipAddress, 'Post',
                                $url, $_SERVER['SERVER_PORT'], $yourbrowser,
                                $correcto, $mensaje ));

		return($stmt);

	}

	function Close() {
    	$this->conn = null;
    }
}

?>