@extends('layouts.storefront-public')

@section('title','Términos de compra y reserva | CommerceOps')
@section('description','Condiciones de reserva, compra, pago, retiro, documentación y garantía de CommerceOps.')

@section('content')
<section class="phase1-page-hero phase1-page-hero-compact">
    <div class="phase1-container">
        <p class="phase1-eyebrow">Información legal</p>
        <h1>Términos de compra y reserva</h1>
        <p class="phase1-lead">Condiciones claras para reservar online, coordinar el pago o completar una compra web cuando esté disponible la pasarela.</p>
    </div>
</section>

<article class="phase1-legal phase1-container">
    <section>
        <h2>Alcance</h2>
        <p>Estos términos aplican a las reservas y compras realizadas desde el storefront de CommerceOps. La información de precios, colores, configuración y disponibilidad puede requerir confirmación final por nuestro equipo antes de entregar el vehículo.</p>
    </section>

    <section>
        <h2>Reserva online con efectivo coordinado</h2>
        <p>Si elegís efectivo, la web registra una reserva y bloquea temporalmente la unidad seleccionada. Esa reserva no es una venta final ni comprobante de compra. Te enviaremos la confirmación por correo y te pediremos continuar por WhatsApp para coordinar el pago y la visita.</p>
        <p>La reserva tiene vencimiento. Si no se coordina o confirma dentro del plazo informado, la unidad puede volver a quedar disponible para otros clientes.</p>
    </section>

    <section>
        <h2>Pago online con tarjeta</h2>
        <p>Mientras Getnet no esté integrado, el pago con tarjeta funciona únicamente en modo de pruebas y no solicita datos reales de tarjeta. Cuando la pasarela quede activa, un pago aprobado confirmará automáticamente la compra web, generará la venta correspondiente y habilitará el comprobante de compra.</p>
    </section>

    <section>
        <h2>Retiro y coordinación</h2>
        <p>No realizamos envíos desde la tienda online. El retiro se coordina previamente por WhatsApp o por los canales oficiales de CommerceOps. Atendemos en la zona de Belvedere, Montevideo, con visita previamente coordinada.</p>
    </section>

    <section>
        <h2>Documentación para circular</h2>
        <p>Los vehículos deben utilizarse cumpliendo la normativa vigente. Según el caso, pueden requerir empadronamiento, seguro SOA, casco y libreta G1 o G2. El precio publicado no incluye empadronamiento, seguro ni otros trámites o accesorios no indicados expresamente. Estos vehículos no pagan patente.</p>
    </section>

    <section>
        <h2>Qué incluye la compra</h2>
        <p>La compra incluye el vehículo seleccionado y su cargador compatible. Empadronamiento, seguro SOA, casco, accesorios, envíos, trámites y otros conceptos no están incluidos salvo que se indique expresamente en una cotización o comprobante.</p>
    </section>

    <section>
        <h2>Garantía y mantenimiento</h2>
        <p>La garantía CommerceOps cubre defectos de fabricación o fallas de origen en motor eléctrico, controladora, sistema eléctrico original, cargador original y batería según condiciones específicas.</p>
        <p>El período de garantía es de 1 año o 6000 km, lo que ocurra primero. Para mantener la cobertura, el vehículo debe presentarse a los 4 mantenimientos correspondientes, establecidos cada 3 meses o cada 1500 km durante el primer año.</p>
        <p>La garantía no cubre daños por golpes, choques, accidentes, uso indebido o negligente, modificaciones no autorizadas, ingreso excesivo de agua, sobrecarga del vehículo, falta de mantenimiento o desgaste normal por uso.</p>
        <p>Para solicitar garantía, el cliente debe comunicarse con CommerceOps, presentar comprobante de compra o factura, entregar el vehículo para inspección técnica y esperar el diagnóstico correspondiente. CommerceOps determinará si la falla corresponde a garantía o a una reparación fuera de cobertura.</p>
    </section>

    <section>
        <h2>Condiciones de uso recomendadas</h2>
        <p>El vehículo debe utilizarse de forma responsable y conforme a la normativa vigente. Las cargas deben realizarse exclusivamente con el cargador suministrado u original compatible. Se debe evitar la exposición prolongada a la lluvia, no circular por zonas con agua estancada y no modificar componentes eléctricos o mecánicos sin autorización.</p>
        <p>Para cuidar la batería, no debe dejarse completamente descargada durante períodos prolongados, no debe sobrecargarse cuando ya está al 100% y se recomienda no dejarla descargar por debajo del 10%.</p>
    </section>

    <section>
        <h2>Cancelaciones y vencimientos</h2>
        <p>Podés cancelar una reserva pendiente desde tu cuenta si todavía no fue confirmada. Si una reserva vence o se cancela, la unidad vuelve a quedar disponible y el pedido queda registrado con trazabilidad operativa.</p>
    </section>

    <section>
        <h2>Privacidad</h2>
        <p>El tratamiento de datos personales se rige por nuestra <a href="{{ route('privacidad') }}">política de privacidad</a>. Usamos los datos necesarios para gestionar cuenta, reserva, compra, facturación, coordinación, garantía y postventa.</p>
    </section>

    <p class="phase1-legal-update">Versión 2026-07 · última actualización: 31/07/2026.</p>
</article>
@endsection
