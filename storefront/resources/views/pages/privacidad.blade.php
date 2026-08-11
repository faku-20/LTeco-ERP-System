@extends('layouts.storefront-public')
@section('title','Política de privacidad | CommerceOps')
@section('description','Cómo CommerceOps trata y protege los datos personales de sus clientes.')
@section('content')
@php($contact=config('storefront_content.contact'))
<section class="phase1-page-hero phase1-page-hero-compact"><div class="phase1-container"><p class="phase1-eyebrow">Información legal</p><h1>Política de privacidad</h1><p class="phase1-lead">Qué información utilizamos, para qué y cómo podés ejercer tus derechos.</p></div></section>
<article class="phase1-legal phase1-container">
<section><h2>Responsable y alcance</h2><p>CommerceOps es responsable del tratamiento de los datos utilizados para administrar esta tienda, las cuentas de clientes, las compras, la facturación, la entrega, la garantía, los servicios técnicos y la atención posventa.</p></section>
<section><h2>Datos tratados</h2><p>Según la gestión realizada, podemos tratar nombre, apellido, correo, teléfono, cédula o RUT, razón social, dirección de facturación, solicitudes de visita, modelo de interés, pedidos, comprobantes, unidades adquiridas, garantía, servicios técnicos, consentimientos y registros de seguridad. No almacenamos contraseñas legibles ni los datos completos de tarjetas; la futura pasarela de pago deberá procesarlos en su propio entorno certificado.</p></section>
<section><h2>Finalidades</h2><p>Usamos los datos necesarios para autenticar la cuenta, verificar identidad, reservar stock, ejecutar y documentar compras, coordinar el retiro con agenda previa, prevenir fraude, cumplir obligaciones legales y brindar garantía y postventa. No usamos decisiones automatizadas con efectos jurídicos ni vendemos bases de clientes.</p></section>
<section><h2>Seguridad y destinatarios</h2><p>Los campos de identidad y dirección se cifran en almacenamiento; el acceso interno se limita por función y las operaciones relevantes quedan auditadas. Podemos utilizar proveedores de infraestructura, correo y, en el futuro, pagos, únicamente para prestar esos servicios y bajo las condiciones aplicables. Las contraseñas se almacenan mediante hash irreversible.</p></section>
<section><h2>Cookies y tecnologías similares</h2><p>La tienda puede usar cookies técnicas necesarias para iniciar sesión, mantener el carrito, recordar la sesión, proteger formularios y operar el checkout. Estas cookies son necesarias para prestar el servicio y no requieren consentimiento adicional.</p><p>Si más adelante incorporamos herramientas de analítica, píxeles publicitarios, remarketing u otras cookies no esenciales, se mostrará un aviso específico para informar su uso y permitir la gestión del consentimiento correspondiente.</p></section>
<section><h2>Conservación</h2><p>Conservamos la información mientras la cuenta o la relación comercial estén activas y posteriormente durante los plazos necesarios para facturación, defensa de derechos, garantía, seguridad y demás obligaciones aplicables. Una solicitud de supresión no elimina automáticamente documentación que deba conservarse; informaremos qué se eliminó, anonimizó o retuvo y por qué.</p></section>
<section><h2>Tus derechos</h2><p>Podés solicitar información, acceso, rectificación, actualización, oposición o supresión desde “Mi cuenta” o por nuestros canales oficiales. Antes de entregar o modificar información verificaremos la identidad. Procuraremos responder dentro de 5 días hábiles, conforme a la orientación de la URCDP.</p><p>También podés consultar o denunciar ante la <a href="https://www.gub.uy/unidad-reguladora-control-datos-personales/" target="_blank" rel="noopener noreferrer">Unidad Reguladora y de Control de Datos Personales</a>.</p></section>
<section>
    <h2>Contacto</h2>
    <p>Para consultas de privacidad utilizá nuestros canales oficiales.
    @if(($contact['email']??'')!=='')
        Correo: <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a>.
    @endif
    </p>
</section>
<p class="phase1-legal-update">Versión 2026-07 · última actualización: 31/07/2026.</p>
</article>
@endsection
