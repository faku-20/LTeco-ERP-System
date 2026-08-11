<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__, 2) . '/shared/mailer.php';

function ecommerceSessionStart(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('lteco_shop');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => requestIsHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function ecommerceLegacyCommercialGone(string $route): never
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET' || $method === 'HEAD') {
        $target = $route === 'carrito' ? 'carrito' : 'comprar';
        publicRedirect(storefrontPublicUrl($target));
    }

    http_response_code(410);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Esta vía comercial legacy fue retirada. Usá la tienda actual de ' . htmlspecialchars(appName(), ENT_QUOTES, 'UTF-8') . '.';
    exit;
}

function ecommerceCsrf(): string
{
    ecommerceSessionStart();
    if (empty($_SESSION['shop_csrf'])) $_SESSION['shop_csrf'] = bin2hex(random_bytes(32));
    return (string) $_SESSION['shop_csrf'];
}

function ecommerceCsrfInput(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(ecommerceCsrf(), ENT_QUOTES, 'UTF-8') . '">';
}

function ecommerceVerifyCsrf(): void
{
    if (!hash_equals(ecommerceCsrf(), (string) ($_POST['csrf'] ?? ''))) {
        throw new RuntimeException('La sesión del formulario venció. Volvé a intentarlo.');
    }
}

function ecommerceCuenta(PDO $pdo): ?array
{
    ecommerceSessionStart();
    $id = (int) ($_SESSION['shop_account_id'] ?? 0);
    if ($id <= 0) return null;
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_cuenta WHERE IdCuenta = ? AND Estado = 'Activa' AND CorreoVerificadoEn IS NOT NULL LIMIT 1");
    $stmt->execute([$id]);
    $cuenta = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$cuenta) unset($_SESSION['shop_account_id']);
    return $cuenta;
}

function ecommerceExigirCuenta(PDO $pdo, string $volver = ''): array
{
    $cuenta = ecommerceCuenta($pdo);
    if ($cuenta) return $cuenta;
    ecommerceSessionStart();
    if ($volver !== '') $_SESSION['shop_after_login'] = $volver;
    publicRedirect(publicBaseUrl('cuenta.php?requerida=1'));
}

function ecommerceLogin(PDO $pdo, string $correo, string $clave): bool
{
    ecommerceSessionStart();
    $correo = mb_strtolower(trim($correo), 'UTF-8');
    $origen=(string)($_SERVER['REMOTE_ADDR']??'local');$rateKey=hash('sha256',$origen.'|'.$correo.'|'.(string)configEnv('LTECO_COMPROBANTE_SECRET','lteco'));
    $s=$pdo->prepare('SELECT Intentos,VentanaInicio FROM ecommerce_limite_acceso WHERE ClaveHash=?');$s->execute([$rateKey]);$rate=$s->fetch(PDO::FETCH_ASSOC);
    if($rate&&strtotime((string)$rate['VentanaInicio'])>time()-900&&(int)$rate['Intentos']>=10)throw new RuntimeException('Demasiados intentos. Esperá 15 minutos antes de probar nuevamente.');
    $stmt = $pdo->prepare('SELECT * FROM ecommerce_cuenta WHERE Correo = ? LIMIT 1');
    $stmt->execute([$correo]);
    $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cuenta) password_verify($clave, '$2y$10$123456789012345678901u7QO5qSNnK6xv56wH8Yk7h0NpqREj1u');
    if (!$cuenta || (!empty($cuenta['BloqueadaHasta']) && strtotime((string) $cuenta['BloqueadaHasta']) > time()) || !password_verify($clave, (string) $cuenta['ClaveHash'])) {
        $pdo->prepare("INSERT INTO ecommerce_limite_acceso (ClaveHash,VentanaInicio,Intentos) VALUES (?,NOW(),1) ON DUPLICATE KEY UPDATE Intentos=IF(VentanaInicio<NOW()-INTERVAL 15 MINUTE,1,Intentos+1),VentanaInicio=IF(VentanaInicio<NOW()-INTERVAL 15 MINUTE,NOW(),VentanaInicio)")->execute([$rateKey]);
        if ($cuenta) $pdo->prepare('UPDATE ecommerce_cuenta SET IntentosFallidos=IntentosFallidos+1,BloqueadaHasta=IF(IntentosFallidos+1>=5,DATE_ADD(NOW(),INTERVAL 15 MINUTE),BloqueadaHasta) WHERE IdCuenta=?')->execute([(int) $cuenta['IdCuenta']]);
        return false;
    }
    if ($cuenta['Estado'] !== 'Activa' || empty($cuenta['CorreoVerificadoEn'])) {
        throw new RuntimeException('Primero verificá tu correo. Si no encontrás el mensaje, podés reenviarlo.');
    }
    session_regenerate_id(true);
    $_SESSION['shop_account_id'] = (int) $cuenta['IdCuenta'];
    $pdo->prepare('DELETE FROM ecommerce_limite_acceso WHERE ClaveHash=?')->execute([$rateKey]);
    $pdo->prepare('UPDATE ecommerce_cuenta SET UltimoAccesoEn=NOW(),IntentosFallidos=0,BloqueadaHasta=NULL WHERE IdCuenta=?')->execute([(int) $cuenta['IdCuenta']]);
    return true;
}

function ecommerceMetrica(PDO $pdo,string $evento): void
{
    if(!preg_match('/^[a-z0-9_]{2,50}$/',$evento))return;
    try{$pdo->prepare('INSERT INTO ecommerce_metrica_diaria (Fecha,Evento,Cantidad) VALUES (CURDATE(),?,1) ON DUPLICATE KEY UPDATE Cantidad=Cantidad+1')->execute([$evento]);}catch(Throwable $e){error_log('[ECOMMERCE_METRIC] '.$e->getMessage());}
}

function ecommerceCrearToken(PDO $pdo, int $cuentaId, string $tipo, int $minutos): string
{
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('UPDATE ecommerce_token SET UsadoEn=NOW() WHERE IdCuenta=? AND Tipo=? AND UsadoEn IS NULL')->execute([$cuentaId, $tipo]);
    $pdo->prepare('INSERT INTO ecommerce_token (IdCuenta,Tipo,TokenHash,ExpiraEn) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL ? MINUTE))')
        ->execute([$cuentaId, $tipo, hash('sha256', $token), $minutos]);
    return $token;
}

function ecommerceEnviarCorreoCliente(string $destino, string $asunto, string $titulo, string $mensaje, string $boton, string $url): bool
{
    if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) return false;
    $html = '<!doctype html><html><body style="font-family:Arial,sans-serif;background:#f4f4f4;padding:20px"><div style="max-width:520px;margin:auto;background:#fff;border-radius:12px;padding:28px"><h1 style="color:#0f6b38">' . htmlspecialchars($titulo) . '</h1><p>' . htmlspecialchars($mensaje) . '</p><p><a style="display:inline-block;background:#159447;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none" href="' . htmlspecialchars($url, ENT_QUOTES) . '">' . htmlspecialchars($boton) . '</a></p><p style="font-size:12px;color:#667">Si no solicitaste esto, ignorá el mensaje.</p></div></body></html>';
    $enviado = lteco_smtp_send_recipients([$destino], $asunto, $html);
    error_log('[ECOMMERCE_MAIL] tipo=' . preg_replace('/[^a-z0-9_-]/i', '-', $titulo) . ' smtp=' . ($enviado ? 'accepted' : 'failed') . ' dominio=' . substr(strrchr($destino, '@') ?: '', 1));
    return $enviado;
}

function ecommerceEnviarVerificacion(PDO $pdo, int $cuentaId, string $correo): bool
{
    $token = ecommerceCrearToken($pdo, $cuentaId, 'VerificarCorreo', 1440);
    $url = publicAbsoluteUrl('cuenta.php?verificar=' . rawurlencode($token));
    $enviado = ecommerceEnviarCorreoCliente($correo, 'Verificá tu cuenta ' . appName(), 'Verificá tu correo', 'Confirmá tu correo para poder iniciar sesión y comprar.', 'Verificar cuenta', $url);
    if (!$enviado && appDebug()) {
        ecommerceSessionStart();
        $_SESSION['shop_debug_link'] = $url;
    }
    return $enviado;
}

/** @return array{id:int,correo_enviado:bool} */
function ecommerceRegistrar(PDO $pdo, array $datos): array
{
    $correo = mb_strtolower(trim((string) ($datos['correo'] ?? '')), 'UTF-8');
    $nombre = trim((string) ($datos['nombre'] ?? ''));
    $apellido = trim((string) ($datos['apellido'] ?? ''));
    $telefono = trim((string) ($datos['telefono'] ?? ''));
    $cedula = trim((string) ($datos['cedula'] ?? ''));
    $tipoCliente = (string) ($datos['tipo_cliente'] ?? 'ConsumidorFinal');
    $rut = trim((string) ($datos['rut'] ?? ''));
    $direccion = trim((string) ($datos['direccion'] ?? ''));
    $clave = (string) ($datos['clave'] ?? '');
    $confirmarClave = (string) ($datos['confirmar_clave'] ?? '');
    if (!in_array($tipoCliente, ['ConsumidorFinal','Empresa'], true)) throw new RuntimeException('El tipo de cliente no es válido.');
    if ($nombre === '' || $apellido === '' || $telefono === '' || $direccion === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Completá todos los datos obligatorios.');
    if ($tipoCliente === 'ConsumidorFinal' && $cedula === '') throw new RuntimeException('Ingresá la cédula para consumidor final.');
    if ($tipoCliente === 'Empresa' && $rut === '') throw new RuntimeException('Ingresá el RUT de la empresa.');
    if ($clave !== $confirmarClave) throw new RuntimeException('Las contraseñas no coinciden.');
    if (strlen($clave) < 10 || !preg_match('/[A-Za-z]/', $clave) || !preg_match('/\d/', $clave)) throw new RuntimeException('La contraseña debe tener al menos 10 caracteres, una letra y un número.');
    try {
        $stmt = $pdo->prepare("INSERT INTO ecommerce_cuenta (TipoCliente,Correo,ClaveHash,Nombre,Apellido,Telefono,Cedula,Rut,Direccion,Estado) VALUES (?,?,?,?,?,?,?,?,?,'Pendiente')");
        $stmt->execute([$tipoCliente,$correo,password_hash($clave,PASSWORD_DEFAULT),mb_substr($nombre,0,100),mb_substr($apellido,0,100),mb_substr($telefono,0,30),$cedula!==''?mb_substr($cedula,0,40):null,$rut!==''?mb_substr($rut,0,40):null,mb_substr($direccion,0,255)]);
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000') throw new RuntimeException('Ya existe una cuenta con ese correo.');
        throw $e;
    }
    $id = (int) $pdo->lastInsertId();
    return ['id' => $id, 'correo_enviado' => ecommerceEnviarVerificacion($pdo, $id, $correo)];
}

function ecommerceVerificarCorreo(PDO $pdo, string $token): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return false;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT IdToken,IdCuenta FROM ecommerce_token WHERE Tipo='VerificarCorreo' AND TokenHash=? AND UsadoEn IS NULL AND ExpiraEn>NOW() FOR UPDATE");
        $stmt->execute([hash('sha256', $token)]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { $pdo->rollBack(); return false; }
        $pdo->prepare('UPDATE ecommerce_token SET UsadoEn=NOW() WHERE IdToken=?')->execute([(int) $row['IdToken']]);
        $pdo->prepare("UPDATE ecommerce_cuenta SET Estado='Activa',CorreoVerificadoEn=COALESCE(CorreoVerificadoEn,NOW()) WHERE IdCuenta=?")->execute([(int) $row['IdCuenta']]);
        $pdo->commit(); return true;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function ecommerceSolicitarRecuperacion(PDO $pdo, string $correo): void
{
    $correo = mb_strtolower(trim($correo), 'UTF-8');
    $stmt = $pdo->prepare("SELECT IdCuenta,Correo FROM ecommerce_cuenta WHERE Correo=? AND Estado<>'Bloqueada' LIMIT 1");
    $stmt->execute([$correo]); $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cuenta) return;
    $token = ecommerceCrearToken($pdo, (int) $cuenta['IdCuenta'], 'RestablecerClave', 30);
    $url = publicAbsoluteUrl('cuenta.php?restablecer=' . rawurlencode($token));
    $enviado = ecommerceEnviarCorreoCliente($correo, 'Restablecé tu contraseña', 'Nueva contraseña', 'Este enlace vence en 30 minutos.', 'Elegir nueva contraseña', $url);
    if (!$enviado && appDebug()) { ecommerceSessionStart(); $_SESSION['shop_debug_link'] = $url; }
}

function ecommerceRestablecerClave(PDO $pdo, string $token, string $clave): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token) || strlen($clave) < 10 || !preg_match('/[A-Za-z]/', $clave) || !preg_match('/\d/', $clave)) return false;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT IdToken,IdCuenta FROM ecommerce_token WHERE Tipo='RestablecerClave' AND TokenHash=? AND UsadoEn IS NULL AND ExpiraEn>NOW() FOR UPDATE");
        $stmt->execute([hash('sha256', $token)]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { $pdo->rollBack(); return false; }
        $pdo->prepare('UPDATE ecommerce_token SET UsadoEn=NOW() WHERE IdToken=?')->execute([(int) $row['IdToken']]);
        $pdo->prepare('UPDATE ecommerce_cuenta SET ClaveHash=?,ClaveCambiadaEn=NOW(),IntentosFallidos=0,BloqueadaHasta=NULL WHERE IdCuenta=?')->execute([password_hash($clave, PASSWORD_DEFAULT), (int) $row['IdCuenta']]);
        $pdo->commit(); return true;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function ecommerceActualizarPerfil(PDO $pdo, array $cuenta, array $datos): void
{
    $nombre=trim((string)($datos['nombre']??''));$apellido=trim((string)($datos['apellido']??''));
    $telefono=trim((string)($datos['telefono']??''));$direccion=trim((string)($datos['direccion']??''));
    if($nombre===''||$apellido===''||$telefono==='')throw new RuntimeException('Completá nombre, apellido y teléfono.');
    $pdo->beginTransaction();
    try{
        $pdo->prepare('UPDATE ecommerce_cuenta SET Nombre=?,Apellido=?,Telefono=?,Direccion=? WHERE IdCuenta=?')->execute([$nombre,$apellido,$telefono,$direccion!==''?$direccion:null,(int)$cuenta['IdCuenta']]);
        if((int)($cuenta['IdCliente']??0)>0){
            $pdo->prepare('UPDATE cliente SET NombreApellido=?,Telefono=?,Direccion=COALESCE(NULLIF(?,\'\'),Direccion) WHERE IdCliente=?')->execute([trim($nombre.' '.$apellido),preg_replace('/[^0-9+]/','',$telefono),$direccion,(int)$cuenta['IdCliente']]);
        }
        $pdo->prepare("INSERT INTO ecommerce_auditoria (IdCuenta,Accion,EstadoNuevo) VALUES (?,'PerfilActualizado','Completado')")->execute([(int)$cuenta['IdCuenta']]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function ecommerceCrearSolicitudPrivacidad(PDO $pdo, array $cuenta, string $tipo, string $detalle): void
{
    if(!in_array($tipo,['Acceso','Correccion','Supresion'],true))throw new RuntimeException('Tipo de solicitud no válido.');
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM ecommerce_solicitud_privacidad WHERE IdCuenta=? AND Tipo=? AND Estado IN ('Pendiente','EnProceso')");$stmt->execute([(int)$cuenta['IdCuenta'],$tipo]);
    if((int)$stmt->fetchColumn()>0)throw new RuntimeException('Ya tenés una solicitud de este tipo en proceso.');
    $pdo->prepare('INSERT INTO ecommerce_solicitud_privacidad (IdCuenta,Tipo,Detalle) VALUES (?,?,?)')->execute([(int)$cuenta['IdCuenta'],$tipo,mb_substr(trim($detalle),0,1000)?:null]);
    $pdo->prepare("INSERT INTO ecommerce_auditoria (IdCuenta,Accion,EstadoNuevo) VALUES (?,'SolicitudPrivacidad','Pendiente')")->execute([(int)$cuenta['IdCuenta']]);
}

/** @return array{garantias:list<array<string,mixed>>,services:list<array<string,mixed>>,solicitudes:list<array<string,mixed>>} */
function ecommerceDatosCuenta(PDO $pdo, array $cuenta): array
{
    $clienteId=(int)($cuenta['IdCliente']??0);$garantias=[];$services=[];
    if($clienteId>0){
        $s=$pdo->prepare('SELECT g.IdGarantia,g.IdVehiculo,g.FechaInicio,g.FechaFin,g.Estado,v.Modelo FROM garantia g JOIN vehiculo v ON v.IdVehiculo=g.IdVehiculo WHERE g.IdCliente=? ORDER BY g.IdGarantia DESC');$s->execute([$clienteId]);$garantias=$s->fetchAll(PDO::FETCH_ASSOC);
        $s=$pdo->prepare('SELECT sv.IdService,sv.IdVehiculo,sv.NumeroService,sv.FechaProgramada,sv.FechaRealizada,sv.Estado,v.Modelo FROM service_vehiculo sv JOIN vehiculo v ON v.IdVehiculo=sv.IdVehiculo WHERE sv.IdCliente=? ORDER BY sv.FechaProgramada DESC');$s->execute([$clienteId]);$services=$s->fetchAll(PDO::FETCH_ASSOC);
    }
    $s=$pdo->prepare('SELECT Tipo,Estado,CreadoEn,ResueltoEn,Respuesta FROM ecommerce_solicitud_privacidad WHERE IdCuenta=? ORDER BY IdSolicitud DESC');$s->execute([(int)$cuenta['IdCuenta']]);
    return ['garantias'=>$garantias,'services'=>$services,'solicitudes'=>$s->fetchAll(PDO::FETCH_ASSOC)];
}

function ecommercePedidoPorToken(PDO $pdo, string $token, ?int $cuentaId = null): ?array
{
    $sql='SELECT * FROM ecommerce_pedido WHERE TokenPublico=?';$params=[$token];if($cuentaId!==null){$sql.=' AND IdCuenta=?';$params[]=$cuentaId;}
    $stmt=$pdo->prepare($sql.' LIMIT 1');$stmt->execute($params);$p=$stmt->fetch(PDO::FETCH_ASSOC);if(!$p)return null;
    $stmt=$pdo->prepare('SELECT * FROM ecommerce_pedido_item WHERE IdPedido=?');$stmt->execute([(int)$p['IdPedido']]);$p['items']=$stmt->fetchAll(PDO::FETCH_ASSOC);return $p;
}
