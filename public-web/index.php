<?php
require_once dirname(__DIR__).'/shared/db.php';
require_once __DIR__.'/includes/helpers.php';
$pageTitle=appName().' | Motos eléctricas en Uruguay';
$empresaPublica=obtenerEmpresaPublica($pdo);$configPublica=obtenerConfiguracionPublica($pdo);
$tieneSlug=dbTieneColumnaPublic($pdo,'producto','Slug');$tieneDescripcionWeb=dbTieneColumnaPublic($pdo,'producto','DescripcionWeb');
$sql="SELECT v.IdVehiculo,v.Modelo,v.CapacidadBateriaAh,v.Color,p.Descripcion,p.PrecioVenta,p.Moneda,p.Estado,p.Stock,p.MostrarEnWeb".($tieneSlug?',p.Slug':'').($tieneDescripcionWeb?',p.DescripcionWeb':'').",(SELECT vi.RutaImagen FROM vehiculo_imagen vi WHERE vi.IdVehiculo=v.IdVehiculo ORDER BY vi.EsPrincipal DESC,vi.OrdenImagen ASC LIMIT 1) ImagenPrincipal FROM vehiculo v JOIN producto p ON p.IdProducto=v.IdProducto WHERE p.MostrarEnWeb=1 AND p.PrecioVenta>0 ORDER BY FIELD(v.Modelo,'Q8-500W','SL-500W'),v.CapacidadBateriaAh,v.IdVehiculo";
$filas=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC)?:[];$modelos=[];
foreach($filas as$f){$nombre=(string)$f['Modelo'];if(!isset($modelos[$nombre]))$modelos[$nombre]=['nombre'=>$nombre,'precio'=>(float)$f['PrecioVenta'],'moneda'=>$f['Moneda'],'imagen'=>$f['ImagenPrincipal'],'id'=>$f['IdVehiculo'],'slug'=>$f['Slug']??'','descripcion'=>$f['DescripcionWeb']??$f['Descripcion']??'','colores'=>[],'disponible'=>false];$modelos[$nombre]['precio']=min($modelos[$nombre]['precio'],(float)$f['PrecioVenta']);$color=trim((string)($f['Color']??''));if($color!==''&&!isset($modelos[$nombre]['colores'][$color]))$modelos[$nombre]['colores'][$color]=['id'=>$f['IdVehiculo'],'slug'=>$f['Slug']??''];if($f['Estado']==='Disponible'&&(int)$f['Stock']>0)$modelos[$nombre]['disponible']=true;}
$modelos=array_values(array_filter($modelos,static fn(array$m):bool=>in_array($m['nombre'],['Q8-500W','SL-500W'],true)));

$imagenesPortada=['Q8-500W'=>'assets/img/Q8 500 AC.webp','SL-500W'=>'assets/img/heroPrincipal.webp'];
foreach($modelos as&$modeloPortada){if(isset($imagenesPortada[$modeloPortada['nombre']]))$modeloPortada['imagen']=$imagenesPortada[$modeloPortada['nombre']];}unset($modeloPortada);
$hero=$modelos[1]??$modelos[0]??null;
$precioPortada=static fn(float $precio,string $moneda):string=>'$'.number_format($precio,0,',','.').' '.htmlspecialchars($moneda);
$detalleUrl=static function(array$m)use($tieneSlug):string{return publicBaseUrl('detalle.php').'?'.($tieneSlug&&!empty($m['slug'])?'slug='.rawurlencode($m['slug']):'id='.rawurlencode($m['id']));};
$colorClase=static function(string$c):string{$c=mb_strtolower($c,'UTF-8');foreach(['beige'=>'beige','blanco'=>'white','negro'=>'black','rosa'=>'pink','azul'=>'blue','rojo'=>'red','gris'=>'gray']as$needle=>$class)if(str_contains($c,$needle))return$class;return'neutral';};
require __DIR__.'/includes/header.php';?>

<main class="home-editorial">
<section class="editorial-hero">
  <div class="editorial-hero__grid">
    <div class="editorial-hero__copy">
      <h1>Movete<br>diferente</h1>
      <p>Scooters eléctricos pensados para la ciudad.<br>Diseño, autonomía y tecnología para que<br>te muevas con libertad y estilo.</p>
      <div class="hero-actions">
        <a class="btn" href="#modelos">Ver modelos <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
        <a class="btn-outline" href="<?=publicBaseUrl('terminos.php')?>">Cómo comprar</a>
      </div>
      <div class="hero-trust">
        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Stock<br>verificado</span>
        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg> Retiro<br>en local</span>
        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Garantía y<br>service oficial</span>
        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> Hasta 18 cuotas<br>según tarjeta</span>
      </div>
    </div>
    <div class="editorial-hero__media">
      <img src="<?=publicBaseUrl('assets/img/heroPrincipal.webp')?>" alt="Scooter eléctrico CommerceOps en la rambla de Montevideo">
    </div>
  </div>
</section>

<section class="value-strip" aria-label="Beneficios">
  <div class="container value-strip__grid">
    <div>
      <div class="value-strip-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/></svg></div>
      <strong>Movilidad eléctrica</strong>
      <span>Movilidad urbana sin emisiones durante el uso.</span>
    </div>
    <div>
      <div class="value-strip-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg></div>
      <strong>Atención cercana</strong>
      <span>Contacto directo antes y después de tu compra.</span>
    </div>
    <div>
      <div class="value-strip-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div>
      <strong>Repuestos y respaldo</strong>
      <span>Soporte técnico para acompañar tu experiencia.</span>
    </div>
    <div>
      <div class="value-strip-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg></div>
      <strong>Seguridad práctica</strong>
      <span>Alarma y tres formas de arranque según el modelo.</span>
    </div>
  </div>
</section>

<section class="model-showcase" id="modelos">
  <div class="container">
<?php if(!$modelos):?>
    <div class="empty-state"><h3>Próximamente</h3><p>Estamos preparando los modelos disponibles.</p></div>
<?php endif?>
<?php foreach($modelos as$index=>$m):$esQ8=$m['nombre']==='Q8-500W';?>
    <article class="model-editorial <?=$index%2?'model-editorial--reverse':''?>">
      <div class="model-editorial__media">
        <img src="<?=htmlspecialchars($m['imagen'])?>" alt="<?=htmlspecialchars($m['nombre'])?>">
      </div>
      <div class="model-editorial__content">
        <h3><?=htmlspecialchars($m['nombre'])?></h3>
        <div class="model-price"><?php if($esQ8):?><small>Desde</small><?php endif?> <?=$precioPortada($m['precio'],$m['moneda'])?></div>
        <ul class="model-specs">
          <li><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> Motor 500W</li>
          <li><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="16" height="10" rx="2" ry="2"/><line x1="22" y1="11" x2="22" y2="13"/></svg> Autonomía hasta <?=$esQ8?'50':'40'?> km</li>
          <li><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8z"/><path d="M12 12l3-3"/></svg> Velocidad máxima <?=$esQ8?'45':'42'?> km/h</li>
          <li><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/></svg> Frenos a disco</li>
        </ul>
<?php if($esQ8):?>
        <p class="version-note">Disponible con batería de 12Ah o 20Ah. La variante y el precio final se seleccionan en el detalle.</p>
<?php endif?>
<?php if($m['colores']):?>
        <div class="color-choice">
          <span>Colores disponibles</span>
          <div class="color-choice__dots" aria-label="Colores disponibles">
<?php foreach($m['colores'] as$c=>$variant):?>
<?php $v=['id'=>$variant['id'],'slug'=>$variant['slug']];?>
            <a class="color-dot color-dot--<?=$colorClase($c)?>" href="<?=htmlspecialchars($detalleUrl($v))?>" title="Ver <?=htmlspecialchars($c)?>"><span class="sr-only"><?=htmlspecialchars($c)?></span></a>
<?php endforeach?>
          </div>
        </div>
<?php endif?>
<?php if(!$esQ8):?>
        <div class="accessory-preview">
          <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px;"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg> Canasto delantero&nbsp; · &nbsp;Opcional</span>
          <small>Precio próximamente</small>
        </div>
<?php endif?>
        <a class="btn model-cta" href="<?=htmlspecialchars($detalleUrl($m))?>">Ver modelo <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
      </div>
    </article>
<?php endforeach?>
  </div>
</section>

<section class="support-strip" id="service">
  <div class="container support-strip__grid">
    <div>
      <div class="support-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg></div>
      <strong>Garantía y service oficial</strong>
      <span>1 año en repuestos, batería y cargador.</span>
    </div>
    <div>
      <div class="support-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m2 7 44 0"/><path d="M3 9l1 11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2l1-11"/><path d="M16 5a4 4 0 0 0-8 0"/></svg></div>
      <strong>Retiro en local</strong>
      <span>Coordiná tu retiro en nuestro local.</span>
    </div>
    <div>
      <div class="support-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
      <strong>Hasta 18 cuotas según tarjeta</strong>
      <span>Con tarjetas de crédito seleccionadas.</span>
    </div>
    <div>
      <div class="support-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg></div>
      <strong>Estamos para ayudarte</strong>
      <span>WhatsApp 092 000 086<br>Montevideo, Uruguay.</span>
    </div>
  </div>
</section>
</main>
<?php require __DIR__.'/includes/footer.php';?>
