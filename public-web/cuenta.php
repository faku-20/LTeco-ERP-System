<?php
require_once dirname(__DIR__) . '/shared/db.php';
require_once __DIR__ . '/includes/ecommerce.php';
$empresaPublica=obtenerEmpresaPublica($pdo);$pageTitle='Mi cuenta | '.appName();$error='';$mensaje='';ecommerceSessionStart();
if(isset($_GET['salir'])){unset($_SESSION['shop_account_id']);session_regenerate_id(true);publicRedirect(publicBaseUrl('cuenta.php'));}
if(isset($_GET['verificar'])){$mensaje=ecommerceVerificarCorreo($pdo,(string)$_GET['verificar'])?'Tu correo quedó verificado. Ya podés ingresar.':'El enlace de verificación no es válido o venció.';}
if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    ecommerceVerifyCsrf();$accion=(string)($_POST['accion']??'');
    if($accion==='login'){
      if(!ecommerceLogin($pdo,(string)($_POST['correo']??''),(string)($_POST['clave']??'')))throw new RuntimeException('Correo o contraseña incorrectos, o la cuenta está temporalmente bloqueada.');
      $volver=(string)($_SESSION['shop_after_login']??publicBaseUrl('cuenta.php'));unset($_SESSION['shop_after_login']);publicRedirect($volver);
    }
    if($accion==='registro'){$resultado=ecommerceRegistrar($pdo,$_POST);$mensaje=$resultado['correo_enviado']?'Cuenta creada. El servidor de correo aceptó el mensaje; revisá también Spam o Correo no deseado.':'Cuenta creada, pero el correo no pudo salir. Usá “Reenviar verificación” o contactanos.';}
    if($accion==='reenviar'){
      $s=$pdo->prepare("SELECT IdCuenta,Correo FROM ecommerce_cuenta WHERE Correo=? AND CorreoVerificadoEn IS NULL LIMIT 1");$s->execute([mb_strtolower(trim((string)($_POST['correo']??'')),'UTF-8')]);$r=$s->fetch();$aceptado=$r?ecommerceEnviarVerificacion($pdo,(int)$r['IdCuenta'],(string)$r['Correo']):true;$mensaje=$aceptado?'Si la cuenta está pendiente, el servidor aceptó un nuevo mensaje. Revisá también Spam.':'No pudimos enviar el correo en este momento. Intentá nuevamente más tarde.';
    }
    if($accion==='recuperar'){ecommerceSolicitarRecuperacion($pdo,(string)($_POST['correo']??''));$mensaje='Si existe una cuenta con ese correo, enviamos un enlace de recuperación.';}
    if($accion==='restablecer'){
      if(!ecommerceRestablecerClave($pdo,(string)($_POST['token']??''),(string)($_POST['clave']??'')))throw new RuntimeException('El enlace venció o la contraseña no cumple los requisitos.');$mensaje='Contraseña actualizada. Ya podés ingresar.';unset($_GET['restablecer']);
    }
    if($accion==='perfil'){$actual=ecommerceCuenta($pdo);if(!$actual)throw new RuntimeException('La sesión venció.');ecommerceActualizarPerfil($pdo,$actual,$_POST);$mensaje='Tus datos fueron actualizados.';}
    if($accion==='privacidad'){$actual=ecommerceCuenta($pdo);if(!$actual)throw new RuntimeException('La sesión venció.');ecommerceCrearSolicitudPrivacidad($pdo,$actual,(string)($_POST['tipo']??''),(string)($_POST['detalle']??''));$mensaje='Recibimos tu solicitud y quedó registrada.';}
  }catch(Throwable $e){$error=$e->getMessage();}
}
$debugLink=(string)($_SESSION['shop_debug_link']??'');unset($_SESSION['shop_debug_link']);
$cuenta=ecommerceCuenta($pdo);$pedidos=[];$datosCuenta=['garantias'=>[],'services'=>[],'solicitudes'=>[]];if($cuenta){$s=$pdo->prepare('SELECT NumeroPedido,TokenPublico,Estado,EstadoPago,Total,Moneda,CreadoEn,IdVenta FROM ecommerce_pedido WHERE IdCuenta=? ORDER BY IdPedido DESC');$s->execute([$cuenta['IdCuenta']]);$pedidos=$s->fetchAll();$datosCuenta=ecommerceDatosCuenta($pdo,$cuenta);}
require __DIR__.'/includes/header.php';?>
<section class="section"><div class="container account-wrap">
<?php if($error):?><div class="shop-alert shop-alert--error"><?=htmlspecialchars($error)?></div><?php endif?>
<?php if($mensaje):?><div class="shop-alert"><?=htmlspecialchars($mensaje)?></div><?php endif?>
<?php if($debugLink&&appDebug()):?><div class="shop-alert">Modo local: <a href="<?=htmlspecialchars($debugLink)?>">abrir enlace de correo</a>.</div><?php endif?>
<?php if($cuenta):?>
<div class="account-heading"><div><span class="eyebrow">Mi cuenta</span><h1>Hola, <?=htmlspecialchars($cuenta['Nombre'])?></h1><p><?=htmlspecialchars($cuenta['Correo'])?> · correo verificado</p></div><a class="btn-secondary" href="?salir=1">Cerrar sesión</a></div>
<div class="account-dashboard">
<div class="info-box"><h2>Mis pedidos</h2><?php if(!$pedidos):?><p>Todavía no tenés pedidos.</p><?php else:?><div class="orders-list"><?php foreach($pedidos as $p):?><div class="order-row"><span><strong><?=htmlspecialchars($p['NumeroPedido'])?></strong><small><?=htmlspecialchars(date('d/m/Y',strtotime($p['CreadoEn'])))?> · <?=htmlspecialchars($p['Estado'])?></small></span><strong><?=htmlspecialchars(formatoPrecioWeb((float)$p['Total'],$p['Moneda']))?></strong><span class="account-actions"><a href="<?=publicBaseUrl('pedido.php?token='.rawurlencode($p['TokenPublico']))?>">Ver pedido</a><?php if(!empty($p['IdVenta'])):?><a href="<?=publicBaseUrl('comprobante.php?token='.rawurlencode($p['TokenPublico']))?>">Comprobante</a><?php endif?></span></div><?php endforeach?></div><?php endif?></div>
<form method="post" class="info-box shop-form"><h2>Mis datos</h2><?=ecommerceCsrfInput()?><input type="hidden" name="accion" value="perfil"><div class="shop-grid"><label>Nombre<input name="nombre" required value="<?=htmlspecialchars((string)$cuenta['Nombre'])?>"></label><label>Apellido<input name="apellido" required value="<?=htmlspecialchars((string)($cuenta['Apellido']??''))?>"></label></div><div class="shop-grid"><label>Correo verificado<input disabled value="<?=htmlspecialchars((string)$cuenta['Correo'])?>"></label><label>Teléfono<input name="telefono" required value="<?=htmlspecialchars((string)($cuenta['Telefono']??''))?>"></label></div><label>Dirección<input name="direccion" value="<?=htmlspecialchars((string)($cuenta['Direccion']??''))?>"></label><button class="btn">Guardar datos</button></form>
<div class="info-box"><h2>Garantías</h2><?php if(!$datosCuenta['garantias']):?><p>Se muestran después de registrar la entrega de una moto.</p><?php else:?><div class="orders-list"><?php foreach($datosCuenta['garantias'] as $g):?><div class="order-row"><span><strong><?=htmlspecialchars($g['Modelo'].' · '.$g['IdVehiculo'])?></strong><small><?=htmlspecialchars($g['Estado'])?> · <?=htmlspecialchars(date('d/m/Y',strtotime($g['FechaInicio'])))?> al <?=htmlspecialchars(date('d/m/Y',strtotime($g['FechaFin'])))?></small></span></div><?php endforeach?></div><?php endif?></div>
<div class="info-box"><h2>Services</h2><?php if(!$datosCuenta['services']):?><p>Todavía no hay services programados.</p><?php else:?><div class="orders-list"><?php foreach($datosCuenta['services'] as $sv):?><div class="order-row"><span><strong>Service #<?=(int)$sv['NumeroService']?> · <?=htmlspecialchars($sv['Modelo'])?></strong><small><?=htmlspecialchars(date('d/m/Y',strtotime($sv['FechaProgramada'])))?> · <?=htmlspecialchars($sv['Estado'])?></small></span></div><?php endforeach?></div><?php endif?></div>
<form method="post" class="info-box shop-form"><h2>Privacidad y datos</h2><p>Podés solicitar una copia, corrección o supresión. Los comprobantes sujetos a conservación legal no se eliminan.</p><?=ecommerceCsrfInput()?><input type="hidden" name="accion" value="privacidad"><label>Solicitud<select name="tipo" required><option value="Acceso">Acceso a mis datos</option><option value="Correccion">Corrección</option><option value="Supresion">Supresión de cuenta</option></select></label><label>Detalle<textarea name="detalle" maxlength="1000" rows="3"></textarea></label><button class="btn-secondary">Enviar solicitud</button><?php if($datosCuenta['solicitudes']):?><div class="orders-list"><?php foreach($datosCuenta['solicitudes'] as $sp):?><div class="order-row"><span><strong><?=htmlspecialchars($sp['Tipo'])?></strong><small><?=htmlspecialchars($sp['Estado'])?> · <?=htmlspecialchars(date('d/m/Y',strtotime($sp['CreadoEn'])))?></small></span></div><?php endforeach?></div><?php endif?></form>
</div>
<?php elseif(isset($_GET['restablecer'])):?>
<form method="post" class="info-box shop-form"><h1>Elegí una nueva contraseña</h1><?=ecommerceCsrfInput()?><input type="hidden" name="accion" value="restablecer"><input type="hidden" name="token" value="<?=htmlspecialchars((string)$_GET['restablecer'])?>"><label>Nueva contraseña<input type="password" name="clave" required minlength="10" autocomplete="new-password"></label><p class="shop-fineprint">Mínimo 10 caracteres, con una letra y un número.</p><button class="btn">Guardar contraseña</button></form>
<?php else:?>
<?php if(isset($_GET['requerida'])):?><div class="shop-alert">Para comprar necesitás crear una cuenta, verificar tu correo e iniciar sesión.</div><?php endif?>
<div class="auth-grid">
<form method="post" class="info-box shop-form"><h1>Ingresar</h1><?=ecommerceCsrfInput()?><input type="hidden" name="accion" value="login"><label>Correo<input type="email" name="correo" required autocomplete="email"></label><label>Contraseña<input type="password" name="clave" required autocomplete="current-password"></label><button class="btn">Ingresar</button><button class="btn-secondary" name="accion" value="recuperar" formnovalidate>Olvidé mi contraseña</button></form>
<form method="post" class="info-box shop-form register-form register-form--panel"><div class="register-intro"><h2>+ Crear cuenta</h2><p>Completá tus datos para comprar y gestionar pedidos, garantía y services.</p></div><?=ecommerceCsrfInput()?><input type="hidden" name="accion" value="registro">
<div class="register-fields">
<label><span>Tipo de cliente</span><select name="tipo_cliente" id="registerClientType"><option value="ConsumidorFinal">Consumidor final</option><option value="Empresa">Empresa/RUT</option></select></label>
<label><span>Nombre</span><input name="nombre" required autocomplete="given-name" value="<?=htmlspecialchars((string)($_POST['nombre']??''))?>"></label>
<label><span>Apellido</span><input name="apellido" required autocomplete="family-name" value="<?=htmlspecialchars((string)($_POST['apellido']??''))?>"></label>
<label><span>Teléfono</span><input name="telefono" required inputmode="tel" autocomplete="tel" value="<?=htmlspecialchars((string)($_POST['telefono']??''))?>"></label>
<label><span>Correo</span><input type="email" name="correo" required autocomplete="email" value="<?=htmlspecialchars((string)($_POST['correo']??''))?>"></label>
<label><span>Cédula</span><input name="cedula" id="registerCedula" required inputmode="numeric" value="<?=htmlspecialchars((string)($_POST['cedula']??''))?>"></label>
<label class="register-rut"><span>RUT</span><input name="rut" id="registerRut" inputmode="numeric" value="<?=htmlspecialchars((string)($_POST['rut']??''))?>"></label>
<label class="register-address"><span>Dirección</span><input name="direccion" required autocomplete="street-address" value="<?=htmlspecialchars((string)($_POST['direccion']??''))?>"></label>
<label><span>Clave</span><input type="password" name="clave" required minlength="10" autocomplete="new-password"></label>
<label><span>Confirmar clave</span><input type="password" name="confirmar_clave" required minlength="10" autocomplete="new-password"></label>
</div><p class="shop-fineprint">La clave debe tener al menos 10 caracteres, una letra y un número.</p><button class="btn register-submit">Crear cuenta</button><button class="btn-secondary" name="accion" value="reenviar" formnovalidate>Reenviar verificación</button></form>
</div><?php endif?></div></section>
<script nonce="<?=cspNonce()?>">(()=>{const type=document.getElementById('registerClientType'),cedula=document.getElementById('registerCedula'),rut=document.getElementById('registerRut');if(!type)return;const sync=()=>{const empresa=type.value==='Empresa';rut.required=empresa;cedula.required=!empresa;document.querySelector('.register-rut')?.classList.toggle('is-required',empresa)};type.addEventListener('change',sync);sync()})();</script>
<?php require __DIR__.'/includes/footer.php';?>
