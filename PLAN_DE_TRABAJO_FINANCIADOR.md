# PLAN DE TRABAJO: SISTEMA MIKELO
## Gestión de Inventario para Heladería

Documento preparado para: Análisis y aprobación de fases de desarrollo

---

## CONTENIDO DEL PROYECTO

El sistema Mikelo es una plataforma integral de gestión de inventario diseñada específicamente para la operación de una heladería con sucursales. El sistema conecta la planta de producción con múltiples sucursales (algunas propiedad del dueño, otras franquicias), facilitando el control de stock, distribución de productos y gestión operativa.

---

## ESTADO ACTUAL (Fase 1 - COMPLETADA)

### Qué se ha logrado hasta ahora

Durante esta fase inicial, hemos implementado un sistema robusto de gestión de envíos con las siguientes funcionalidades operativas:

1. Alta de Productos en Depósito
   - Los operarios pueden registrar nuevos productos que ingresan al depósito
   - Se asignan contenedores para facilitar el manejo
   - Se valida que la información sea correcta

2. Gestión de Envíos a Sucursales
   - Las sucursales pueden solicitar productos
   - El sistema busca automáticamente los productos disponibles
   - Se utilizan códigos de barras para una búsqueda rápida y precisa
   - Se puede crear y confirmar envíos

3. Visualización de Stock
   - Disponibilidad actualizada en tiempo real de productos en depósito
   - Búsqueda por familia de productos o código
   - Identificación clara de productos agotados

### Validaciones implementadas

El sistema valida automáticamente que:
- No se envíen más productos de los que hay disponibles
- Los códigos de barras se interpreten correctamente
- Los datos ingresados sean precisos
- Se mantenga un registro de quién hace qué y cuándo

### Tests y validación

Hemos probado exhaustivamente el sistema con:
- 5 casos de prueba automatizados (todos pasaron exitosamente)
- Más de 6,400 registros de datos reales de la base de datos
- Simulaciones de escenarios complejos de múltiples referencias
- Validación de cálculos de disponibilidad

El sistema está completamente funcional y listo para usar en producción.

---

## FASE 2: GESTIÓN DE PEDIDOS Y STOCK LOCAL (3 semanas aproximadamente)

### Objetivo

Ampliar el sistema para que las sucursales puedan hacer pedidos formales y gestionar su propio stock local, con seguimiento de recepciones y registro de ventas.

### Semana 1: Preparación de Infraestructura

En esta primera semana estableceremos las bases para que las sucursales gestionen pedidos:

1. Actualización de la base de datos
   - Se agregarán nuevas tablas para almacenar información de pedidos
   - Se configurará el registro de stock en cada sucursal
   - Se preparará el sistema para guardar configuraciones de stock mínimo

2. Desarrollo de API (conexión del sistema)
   - Se crearán puntos de conexión para que las sucursales creen pedidos
   - Se preparará la búsqueda de disponibilidad desde la planta
   - Se establecerá la forma en que se confirman y rechazan pedidos

3. Interfaz para crear pedidos
   - Una pantalla nueva donde las sucursales ven el catálogo completo
   - Capacidad de agregar productos al carrito de pedidos
   - Opción de guardar pedidos en borrador o enviarlos directamente
   - Vista de pedidos anteriores para referencias

### Semana 2: Dashboard de Producción y Recepciones

En la segunda semana conectaremos la planta con las sucursales:

1. Tablero de Producción (para la planta central)
   - Un panel donde el personal de la planta ve todos los pedidos pendientes
   - Agrupa pedidos por estado (pendientes, en preparación, listos)
   - Permite marcar pedidos como preparados
   - Muestra información de qué sucursal pidió qué

2. Módulo de Recepciones
   - Las sucursales reciben los envíos en una pantalla dedicada
   - Se valida que lo recibido coincida con lo enviado
   - Si hay diferencias (rotura, faltante), se registra el motivo
   - Una vez validado, el stock de la sucursal se actualiza automáticamente

3. Módulo de Bajas de Stock
   - Los operarios registran las ventas de cada día
   - Dos formas de registro: por código de barras (rápido) o ajuste manual (por si hay discrepancias)
   - El sistema descuenta automáticamente del stock local

### Semana 3: Refinamiento y Configuraciones

En la tercera semana perfeccionaremos el sistema:

1. Configuración de Stock Mínimo
   - Cada sucursal puede establecer qué cantidad mínima quiere de cada producto
   - El sistema sugiere automáticamente cuándo pedir
   - Alertas cuando el stock cae por debajo del mínimo configurado

2. Ajustes y correcciones
   - Reconciliación de inventarios
   - Reportes de movimientos
   - Corrección de errores de registro anteriores

3. Validación y pruebas
   - Pruebas con datos reales de operación
   - Verificación de cálculos de stock
   - Tests de integración entre planta y sucursales

---

## FASE 3: SISTEMA DE USUARIOS Y SEGURIDAD (2 semanas aproximadamente)

### Objetivo

Implementar un sistema robusto de autenticación y permisos para que cada usuario vea y acceda solo lo que corresponde según su rol.

### Semana 1: Infraestructura de Seguridad

Estableceremos los fundamentos de seguridad:

1. Sistema de Autenticación
   - Login con usuario y contraseña
   - Generación de tokens seguros (JWT)
   - Sesiones que expiran automáticamente
   - Logout y revocación de acceso

2. Base de datos de usuarios
   - Almacenamiento seguro de contraseñas
   - Registro de tokens activos
   - Histórico de accesos

3. Definición de Roles

   Se crearán 6 roles diferentes con permisos específicos:

   a) Administrador del Sistema
      - Acceso total a todas las funciones
      - Puede crear y gestionar cualquier usuario
      - Ve auditoría completa

   b) Supervisor de Planta
      - Supervisa toda la operación de planta
      - Acceso a todos los módulos de producción
      - Puede crear usuarios de planta
      - No puede acceder a datos administrativos del sistema

   c) Administrativo de Planta
      - Gestiona operaciones del depósito central
      - Controla entrada de productos
      - Ve stock disponible
      - Sin acceso a gestión de usuarios

   d) Supervisor de Sucursal
      - Supervisa una o varias sucursales asignadas
      - Puede crear usuarios para sus sucursales
      - Ve pedidos, recepciones y ventas
      - Solo de sus sucursales asignadas

   e) Administrativo de Sucursal
      - Gestiona operaciones de su sucursal
      - Registra ventas
      - Recibe entregas
      - No puede crear ni gestionar otros usuarios

   f) Operario
      - Registra escaneos de productos
      - Carga datos de ventas
      - Sin acceso a reportes ni auditoría

### Semana 2: Gestión de Usuarios

Implementaremos la capacidad de administrar usuarios:

1. Módulo de Gestión de Usuarios
   - El Supervisor de Planta puede crear Administrativos de Planta
   - El Supervisor de Sucursal puede crear Administrativos y Operarios en sus sucursales
   - Cada usuario se asigna a su contexto correspondiente

2. Permisos por Rol
   - Cada rol tiene acceso específico a módulos (Productos, Envíos, Pedidos, Stock, Reportes)
   - Dentro de cada módulo, permisos para ver, crear, editar o eliminar

3. Auditoría
   - Registro de quién hace qué, cuándo y dónde
   - Histórico de accesos
   - Trazabilidad de cambios en datos importantes

---

## RESUMEN DE FUNCIONALIDADES POR FASE

### Fase 1: Completa y Operativa (Lista para Producción)

Módulos activos:
- Alta de productos en depósito
- Creación y gestión de envíos
- Visualización de stock disponible
- Búsqueda de productos por código y familia
- Historial de movimientos

### Fase 2: Gestión Integral (En Planificación)

Nuevos módulos:
- Creación de pedidos desde sucursales
- Dashboard de producción para la planta
- Recepción de entregas en sucursales
- Registro de ventas diarias
- Configuración de stock mínimo
- Alertas automáticas

### Fase 3: Control y Seguridad (En Diseño)

Nuevos módulos:
- Login de usuarios
- Gestión de permisos por rol
- Creación y administración de usuarios
- Auditoría de acciones
- Reportes de seguridad

---

## CONSIDERACIONES ESPECIALES

### Estructura de Sucursales

El sistema reconoce dos tipos de sucursales:

1. Sucursales de Propiedad del Dueño (2 locales)
   - Control total del dueño
   - Acceso completo a reportes
   - Gestión centralizada desde la planta

2. Sucursales en Franquicia (resto de locales)
   - Propietarios independientes
   - Acceso solo a datos de su sucursal
   - Gestión descentralizada

Cada sucursal tendrá un supervisor y personal administrativo asignado, con permisos limitados a su contexto.

### Métodos de Registro de Ventas

Para registrar ventas diarias, el sistema soportará dos métodos:

1. Por Código de Barras (rápido)
   - El operario escanea cada producto vendido
   - El sistema decrementa automáticamente el stock

2. Ajuste Manual (para correcciones)
   - Al final del día, se compara stock teórico vs stock físico
   - Se registran las diferencias
   - Útil para detectar roturas o pérdidas

---

## CRONOGRAMA ESTIMADO

Fase 1: Completada (Noviembre 2025)
- Estado: Funcional y listo para producción
- Próxima acción: Deploy a servidor de producción

Fase 2: Gestión de Pedidos (Diciembre 2025)
- Duración: 3 semanas
- Semana 1: 2-6 de diciembre (Infraestructura)
- Semana 2: 9-13 de diciembre (Dashboard y Recepciones)
- Semana 3: 16-20 de diciembre (Refinamiento)
- Entrega estimada: 23 de diciembre de 2025

Fase 3: Sistema de Usuarios (Enero 2026)
- Duración: 2 semanas
- Semana 1: 6-10 de enero (Infraestructura de Seguridad)
- Semana 2: 13-17 de enero (Gestión de Usuarios)
- Entrega estimada: 20 de enero de 2026

---

## RECURSOS REQUERIDOS

### Para Desarrollo
- Servidor de desarrollo con base de datos MySQL
- Entorno PHP configurado
- Servidor web (Apache o similar)

### Para Testing
- Usuarios de prueba con diferentes roles
- Datos de prueba que simulen operación real
- Acceso a sucursales para validación en campo

### Para Producción
- Servidor de producción dedicado
- Backups automatizados
- Monitoreo de performance

---

## BENEFICIOS ESPERADOS

Con la implementación completa del sistema:

1. Mayor Precisión
   - Registro automático de movimientos
   - Reducción de errores manuales
   - Trazabilidad completa de productos

2. Eficiencia Operativa
   - Búsqueda rápida de disponibilidad
   - Procesos automatizados
   - Menos tiempo en gestión administrativa

3. Control Centralizado
   - Visibilidad en tiempo real del stock
   - Reportes detallados
   - Auditoría de todas las acciones

4. Escalabilidad
   - Soporta múltiples sucursales
   - Fácil de agregar nuevos locales
   - Flexible para futuras expansiones

---

## PRÓXIMOS PASOS INMEDIATOS

1. Aprobación de este plan
2. Deploy de Fase 1 a producción (con backup)
3. Capacitación del personal en el uso del sistema
4. Inicio de Fase 2 (Gestión de Pedidos)

---

## SOPORTE Y MANTENIMIENTO

Durante todas las fases:
- Soporte técnico disponible para consultas
- Corrección de bugs identificados
- Ajustes menores según feedback de usuarios
- Documentación de procesos
- Capacitación del personal operativo

---

Documento preparado: 29 de Noviembre de 2025
Versión: 1.0
Estado: Listo para presentación

