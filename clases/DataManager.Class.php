<?php
class DataManager
{

	//errores Numerico y Mensajes
	private static $error;
	private static $mensajeError;


	static public function getInstance()
	{
		global $dsn;
		$db = null;

		try {
			$db = new PDO("sqlsrv:Server=192.168.25.220;Database=ProcesoNew", 'odbc_integra', 'Us3rInt3gr4');
			$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (exception $e) {
			self::$mensajeError = $e->getMessage();
			self::$error = true;
			echo self::$mensajeError;
			$db = null;
			exit;
		}

		return $db;
	}
}
