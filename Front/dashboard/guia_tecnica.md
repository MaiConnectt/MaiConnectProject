# Guía de Arquitectura Técnica: Mai Connect

Esta guía resume el funcionamiento interno del sistema **Mai Connect** para que puedas estudiarlo y explicar de forma profesional cómo está construido.

## 1. Stack Tecnológico
- **Backend**: PHP 8 (Encargado de procesar la lógica y conectar con la BD).
- **Base de Datos**: PostgreSQL (Donde se guarda toda la información).
- **Frontend**: HTML5, CSS3 y JavaScript (Lo que el usuario ve y con lo que interactúa).
- **Seguridad**: Se usa **PDO** para conectar PHP con la base de datos, lo cual protege contra ataques de Inyección SQL.

## 2. Organización del Código
El proyecto es **Modular**: cada función importante tiene su propia carpeta.
- `/dashboard/pedidos`: Todo lo relacionado con las ventas.
- `/dashboard/productos`: El inventario y catálogo.
- `/dashboard/equipo`: Gestión de los vendedores.
- `/includes`: Piezas que se repiten, como el menú lateral o las ventanas (modals).

## 3. El Flujo de Información (Ejemplo: Un Pedido)
1. **Acción**: El vendedor crea un pedido en su panel.
2. **Transferencia**: JavaScript (en `pedidos.js`) empaqueta los datos y los envía al servidor.
3. **Procesamiento**: PHP recibe los datos, los valida y los guarda en la base de datos.
4. **Automatización (Triggers)**: Al momento de guardarse, la base de datos calcula automáticamente la comisión del vendedor sin que el programador tenga que escribir código extra en PHP.
5. **Resultado**: El administrador ver el nuevo pedido y la comisión actualizada en tiempo real.

## 4. Diferenciadores Técnicos (Valor Agregado)
Para destacar en tu explicación, menciona estos puntos:
- **Soft Delete**: El sistema no "borra" datos por completo; los marca como "inactivos". Esto es una práctica profesional para no perder registros históricos ni descuadrar las finanzas.
- **Seguridad BCrypt**: Las contraseñas se encriptan con un algoritmo de alto nivel. Ni siquiera el administrador puede ver la contraseña de los usuarios.
- **Vistas Inteligentes**: Usamos "Views" en la base de datos para generar reportes complejos de forma instantánea, en lugar de hacer cálculos lentos cada vez que carga la página.
- **Diseño Responsivo**: La interfaz se adapta perfectamente a celulares, permitiendo que los vendedores registren sus ventas desde cualquier lugar.
