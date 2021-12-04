<?php
/**
 * Api session implementation. Simplifica el proceso para el uso de la variable
 * superglobal $_SESSION en entornos donde no se dispone del uso de cookies.
 * Esto se logra mediante el uso algún otro identificador (como puede ser un
 * token de acceso).
 */

namespace Perritu;

class ApiSessions
{
  /**
   * @var string Ruta al archivo de sesion.
   *
   * El primer string `%s` indica la ruta al directorio temporal, el segundo el
   * id del archivo.
   */
  static $SESSION_FILE = '%s/perritu_api_sessions/%s';

  /**
   * @var int Tiempo de vida de la sesión en segundos.
   */
  static $LIFETIME = 1800; // 30 minutos.

  /**
   * @var string Nombre del archivo de sesion actual.
   */
  private $sessionFile;

  /**
   * Centraliza los accesos a la sesión. Se encarga de crear la sesión (si aún
   * no existe).
   *
   * Para manejar la sesión se usa un archivo temporal basado en la dirección IP
   * del cliente y, opcionalmente, un identificador proporcionado durante la
   * llamada.
   *
   * Si la sesión ya ha sido iniciada, se devuelve un apuntador a la misma
   * (incluso si se ha iniciado por otro proceso).
   *
   * @param string $id Identificador de la sesión.
   * @return array Apuntador a la sesión.
   */
  public static function &BLOB(string $id = null)
  {
    // Si ya existe la sesión, devolvemos el apuntador.
    if (isset($_SESSION)) {
      return $_SESSION;
    }

    // ID de la sesión.
    $id = ($id ? "{$id}_" : '') . self::getIp();

    // Inicia la sesión.
    $session = new ApiSessions($id);
    \session_set_save_handler(
      [$session, 'open'],
      [$session, 'close'],
      [$session, 'read'],
      [$session, 'write'],
      [$session, 'destroy'],
      [$session, 'gc']
    );
    \session_start();

    // Devuelve el apuntador.
    return $_SESSION;
  }

  /**
   * Devuelve la dirección IP del cliente.
   *
   * @return string Dirección IP del cliente.
   */
  private static function getIp()
  {
    // Cabeceras en donde buscar.
    $headers = [
      'HTTP_CF_CONNECTING_IP',
      'HTTP_INCAP_CLIENT_IP',
      'HTTP_X_FORWARDED_FOR',
      'HTTP_X_FORWARDED',
      'HTTP_X_CLUSTER_CLIENT_IP',
      'HTTP_FORWARDED_FOR',
      'HTTP_FORWARDED',
      'HTTP_CLIENT_IP',
      'REMOTE_ADDR',
    ];

    foreach ($headers as $header) {
      if (isset($_SERVER[$header])) {
        foreach (explode(',', $_SERVER[$header]) as $ip) {
          $ip = trim($ip);
          if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
          }
        }
      }
    }

    // Si al final no se encuentra una IP (si se hace la petición por CLI, por
    // ejemplo) se entrega la ip null.
    return '0.0.0.0';
  }

  /**
   * Constructor de la clase.
   * Empleado para su uso con la librería estandar de PHP.
   *
   * @param string $id Identificador de la sesión.
   * @return void
   */
  public function __construct(string $id)
  {
    // Nombre del archivo de sesión.
    // Es necesario sustituir el caracteres prohibidos del id.
    $id = preg_replace('/[^a-z0-9.,\-;]/i', '_', $id);

    // Nombre del archivo de sesión.
    $this->sessionFile = sprintf(self::$SESSION_FILE, sys_get_temp_dir(), $id);

    // Se asegura de que el directorio existe.
    @\mkdir(
      dirname($this->sessionFile),
      0777,
      true
    );
  }

  /**
   * Función dummy. Empleado para su uso con la librería estandar de PHP.
   *
   * @param string $savePath    No empleado.
   * @param string $sessionName No empleado.
   * @return bool  true.
   */
  public function open($savePath, $sessionName)
  {
    return true;
  }

  /**
   * Función dummy. Empleado para su uso con la librería estandar de PHP.
   *
   * @return bool true.
   */
  public function close()
  {
    return true;
  }

  /**
   * Devuelve el contenido de la sesión.
   *
   * @param string $sessionId No empleado.
   * @return string Contenido de la sesión.
   */
  public function read($sessionId)
  {
    if(\is_file($this->sessionFile)) {
      \touch($this->sessionFile);
      return @\file_get_contents($this->sessionFile);
    }

    return '';
  }

  /**
   * Guarda el contenido de la sesión.
   *
   * @param string $sessionId No empleado.
   * @param string $data      Contenido de la sesión.
   * @return bool true.
   */
  public function write($sessionId, $data)
  {
    return \file_put_contents($this->sessionFile, $data);
  }

  /**
   * Destruye la sesión.
   *
   * @param string $sessionId No empleado.
   * @return bool true.
   */
  public function destroy($sessionId)
  {
    return \unlink($this->sessionFile);
  }

  /**
   * Destruye las sesiones antiguas.
   *
   * @param int $maxlifetime No empleado.
   * @return bool true.
   */
  public function gc($maxlifetime)
  {
    // Ruta absoluta al directorio de sesiones.
    $sessionDir = \realpath(\dirname(self::$SESSION_FILE));

    // Obtiene los archivos de sesión.
    $files = \scandir($sessionDir);

    // Fecha epoch de expiración.
    $expire = \time() - self::$LIFETIME;

    // Recorre los archivos.
    foreach ($files as $file) {
      // Ruta al archivo.
      $file = "$sessionDir/$file";

      // Si es un archivo expirado.
      if (\is_file($file) && \filemtime($file) < $expire) {
        // Elimina el archivo.
        \unlink($file);
      }
    }
  }
}
