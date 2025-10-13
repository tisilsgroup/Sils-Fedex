# FedEx Chile API Integration

Cliente PHP para integración con la **API de FedEx Chile**, implementando autenticación **OAuth2 (client_credentials)** y operaciones básicas de **creación** y **cancelación de envíos**.  
Permite automatizar la generación de etiquetas, el almacenamiento de respuestas y el registro de auditorías.

- [FedEx Chile API Integration](#fedex-chile-api-integration)
  - [🧾 Versiones](#-versiones)
  - [📦 Descripción](#-descripción)
  - [⚙️ Requisitos](#️-requisitos)
  - [📁 Estructura de Archivos](#-estructura-de-archivos)
  - [🔑 Configuración](#-configuración)
  - [🚀 Ejecución](#-ejecución)
  - [🧾 Auditoría](#-auditoría)


---

## 🧾 Versiones
- 1.0.0 
**20251013** Para validaciones finales por parte de Sils Group

---


## 📦 Descripción

Este script realiza las siguientes tareas principales:

1. Recupera **configuración y envíos pendientes** desde la base de datos (clases `Info` y `Auditoria`).
2. Autentica vía **OAuth2** con credenciales configuradas en la base de datos.
3. Genera envíos mediante el método `createShipment`.
4. Registra **datos maestros, etiquetas y documentos** en la base de datos.
5. Guarda **auditorías** de cada acción ejecutada.
6. Cancela automáticamente cada envío de prueba mediante `cancelShipment`.

---

## ⚙️ Requisitos

- PHP ≥ 7.4  
- Extensiones necesarias: `curl`, `json`, `mbstring`
- Acceso a las clases base:
  - `clases/DataManager.Class.php`
  - `clases/FedexChileApi.Class.php`
  - `clases/Info.Class.php`
  - `clases/Auditoria.Class.php`
- Conectividad a los endpoints de la **API FedEx Chile** (OAuth, Shipment y Cancel).

---

## 📁 Estructura de Archivos

/clases/
├── FedexChileApi.Class.php
├── Info.Class.php
├── DataManager.Class.php
├── Auditoria.Class.php
index.php ← Script principal


---

## 🔑 Configuración

El script obtiene los datos de configuración desde la base de datos a través de `DataManager.Class.php` y `Info.Class.php`.  
Las claves de configuración utilizadas son:

| Campo | Descripción |
|-------|--------------|
| `conf_texto_1`, `conf_texto_2` | Usuario y contraseña (autenticación FedEx) |
| `conf_texto_3` – `conf_texto_6` | Credenciales de cuenta, medidor y llaves WS |
| `conf_texto_7` – `conf_texto_9` | URLs de OAuth, creación y cancelación de envíos |
| `conf_texto_10` – `conf_texto_22` | Datos del remitente (nombre, dirección, contacto, etc.) |
| `conf_texto_23` – `conf_texto_26` | Parámetros de pago, servicios especiales y documentos |

---

## 🚀 Ejecución

Puede ejecutarse desde navegador o desde CLI:

`php index.php`


## 🧾 Auditoría

Cada operación ejecutada se registra mediante la clase Auditoria, incluyendo:

- Nombre del procedimiento ejecutado
- Descripción de la acción
- Tipo de origen (API)
- DML o payload enviado
- Resultado (éxito/error)
- Mensaje de detalle o respuesta JSON