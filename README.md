# Manual de Usuario - GestorTaller

GestorTaller es una aplicación web pensada para llevar el día a día de un taller mecánico: recepción de vehículos, órdenes de reparación, control de recambios, pedidos a proveedores, facturación y gestión de usuarios.

## Índice

- [Acceso a la aplicación](#acceso-a-la-aplicación)
- [Navegación](#navegación)
- [Panel de inicio](#panel-de-inicio)
- [Módulos y cómo usarlos](#módulos-y-cómo-usarlos)
  - [Órdenes de reparación](#1-órdenes-de-reparación)
  - [Clientes](#2-clientes)
  - [Vehículos](#3-vehículos)
  - [Recambios](#4-recambios)
  - [Pedidos de recambios](#5-pedidos-de-recambios)
  - [Facturación](#6-facturación)
  - [Informes](#7-informes)
  - [Usuarios](#8-usuarios)
- [Estados y conceptos](#estados-y-conceptos)
- [Trucos prácticos](#trucos-prácticos)
- [Preguntas frecuentes](#preguntas-frecuentes)

## Acceso a la aplicación

1. Abre tu navegador y entra en: `http://localhost/GestorTaller/`
2. Introduce tu **usuario** y **contraseña**
3. Pulsa **Ingresar**
4. Para salir pulsa **Cerrar sesión** en la barra superior

## Navegación

En la parte superior siempre verás:

- **Gestor del Taller** - Vuelve al panel principal
- **Menú de módulos** - Acceso directo a los módulos que tengas permitidos
- **Usuario · Rol** - Muestra quién está conectado
- **Cerrar sesión** - Finaliza la sesión

## Panel de inicio

Nada más entrar verás el **Panel** con unas tarjetas. Cada tarjeta corresponde a un módulo al que tienes acceso.

Si no aparece ninguna tarjeta, significa que tu rol aún no tiene módulos asignados. En ese caso, contacta con el administrador.

## Módulos y cómo usarlos

### 1. Órdenes de reparación

Aquí se gestiona todo lo relacionado con las reparaciones que entran en el taller.

#### Crear una nueva orden

1. Ve a `Órdenes de reparación` > `Nueva orden`
2. Elige el modo:
   - **Vehículo ya registrado** - Selecciona el vehículo de la lista desplegable
   - **Vehículo nuevo** - Rellena matrícula, marca, modelo, año. Si el cliente no existe, marca `Cliente nuevo` e introduce sus datos
3. En **Trabajo a realizar** describe brevemente qué se va a hacer (ej. "Cambio de pastillas de freno")
4. Pulsa **Crear orden**

#### Asignar mecánico *(Solo Jefe de Taller / Administrador)*

1. Busca la orden en el listado
2. En la columna **Mecánico** selecciona el mecánico correspondiente
3. Pulsa **Asignar**

#### Cambiar estado de la orden

Dependiendo de tu rol podrás hacerlo de dos formas:

- **Si tienes permiso para editar**: Usa el desplegable `Estado` de esa orden y pulsa **Guardar**.
- **Si eres mecánico**: Solo podrás cambiar el estado de **las órdenes que te tengan asignado**.

Los estados son: `Pendiente` → `En curso` → `Listo`

#### Borrar una orden *(Solo con permiso)*

Pulsa **Borrar** en la fila correspondiente. Siempre te pedirá confirmación antes de eliminarla.

> **Truco:** El mecánico normalmente solo verá **sus propias órdenes**, no todas las del taller.

### 2. Clientes

Sirve para tener fichas con los datos de los clientes que pasan por el taller.

#### Ver listado de clientes

Ve a `Clientes`. Puedes buscar por **nombre, NIF, teléfono o matrícula** usando el buscador.

#### Crear un cliente

1. En `Clientes` baja hasta `Nuevo cliente`
2. Rellena al menos el **Nombre** (obligatorio). El resto son opcionales
3. Pulsa **Crear cliente**

#### Ver ficha del cliente

Pulsa sobre el **nombre** del cliente desde el listado. Ahí verás sus datos, sus vehículos y podrás editarlos.

#### Añadir un vehículo al cliente

1. Entra en la ficha del cliente
2. En `Añadir vehículo` introduce **Matrícula** (obligatoria), marca, modelo y año
3. Pulsa **Añadir vehículo**

#### Editar o borrar

- **Editar datos**: Desde la ficha, si tienes permiso, podrás modificar nombre, NIF, teléfono o email y guardar los cambios.
- **Borrar cliente**: Desde la ficha, con permiso de borrado. **Cuidado:** Al borrar el cliente también se eliminan sus vehículos asociados.

### 3. Vehículos

Listado global de todos los vehículos registrados en el taller.

- **Buscar**: Por matrícula, marca, modelo o nombre del cliente
- **Ver ficha**: Pulsa sobre la matrícula para ver sus datos, propietario e **historial de órdenes** de ese vehículo
- **Editar/Borrar**: Disponible según tu rol

Ideal para consultar rápidamente el histórico de un coche sin tener que buscar por cliente.

### 4. Recambios

Control del almacén de recambios/repuestos.

#### Añadir recambio *(Jefe de Recambios / Administrador)*

1. Ve a `Recambios` > `Nuevo recambio`
2. Rellena: **Referencia** (única), **Descripción**, precios, **Stock actual**, **Stock mínimo** y **Proveedor**
3. Pulsa **Crear**

#### Controlar stock

- **Normal** - Aparece con fondo blanco
- **Stock bajo** - Aparece resaltado en **rojo** cuando las existencias son iguales o inferiores al stock mínimo

#### Editar o borrar recambio

Desde el listado, desplega `Editar` para modificar precios, stock o datos. Con permiso de borrado también puedes eliminarlo.

> **Recomendado:** Usa el campo **Stock mínimo** para que la aplicación te avise cuando convenga hacer un pedido.

### 5. Pedidos de recambios

Permite pedir recambios a proveedores y actualizar el stock al recibirlos.

#### Crear un pedido

1. Ve a `Pedidos de recambios` > `Nuevo pedido`
2. Rellena **Proveedor**, **Fecha** y, si lo deseas, **Observaciones**
3. Pulsa **Crear pedido**

Se abrirá automáticamente la ficha del pedido.

#### Añadir líneas al pedido

1. En la ficha del pedido (estado `Pendiente`)
2. Selecciona el **Recambio** de la lista
3. Indica **Cantidad** y **Precio unitario**
4. Pulsa **Añadir**

El **Total** se recalcula solo.

#### Recibir el pedido *(Cuando llegue la mercancía)*

1. Abre el pedido en estado `Pendiente`
2. Pulsa **Marcar recibido (sumar stock)**
3. Al confirmarlo, las cantidades se sumarán automáticamente al **stock** de cada recambio

#### Cancelar un pedido

Si aún está en `Pendiente`, puedes pulsar **Cancelar**. **Solo funciona con pedidos pendientes.** Los pedidos ya recibidos **no se pueden cancelar**.

### 6. Facturación

Permite facturar las reparaciones realizadas.

#### Facturar desde una orden lista

Lo más habitual:

1. Ve a `Facturación` > `Crear factura`
2. En `Desde orden lista (sin facturar)` selecciona la orden que esté en estado `Listo`
3. Pulsa **Crear desde orden**

Se creará la factura con el cliente y el trabajo de esa orden.

#### Crear factura manual

Si no viene de una orden:

1. Ve a `Facturación` > `Crear factura`
2. En `Manual` selecciona el **Cliente** y la **Fecha**
3. Pulsa **Crear manual**

#### Añadir conceptos a la factura

1. Abre la factura desde el listado
2. En `Añadir línea` introduce **Concepto** (mano de obra, recambios, etc.), **Cantidad** y **Precio unitario**
3. Pulsa **Añadir**

La aplicación calcula automáticamente **Subtotal**, **IVA (21%)** y **Total**.

#### Cambiar estado de la factura

Desde la ficha de la factura puedes pasarla a:

- `Pendiente` - Aún sin cobrar
- `Pagada` - Ya cobrada
- `Anulada` - Anulada (no se borra el registro)

#### Borrar factura *(Solo con permiso)*

Desde la ficha, con permiso de borrado y confirmación. Se recomienda **anularla** en lugar de borrarla si ya tiene histórico.

### 7. Informes

Vista rápida para ver la situación del taller de un vistazo.

Encontrarás:

- **Órdenes totales / sin asignar** - Saber qué queda por asignar
- **Facturación pendiente / pagada** - Control de cobros
- **Recambios con stock bajo** - Aviso para realizar pedidos
- **Órdenes por estado** - Distribución actual
- **Top mecánicos** - Quién lleva más órdenes
- **Últimas órdenes y últimos clientes** - Actividad reciente

### 8. Usuarios

**Solo accesible para el Administrador.**

#### Crear nuevo usuario

1. Ve a `Usuarios` > `Nuevo usuario`
2. Rellena **Usuario**, **Contraseña** (mínimo 8 caracteres) y **Rol**
3. Pulsa **Crear usuario**

#### Cambiar rol a un usuario

1. En el listado, usa el desplegable de **Rol** junto al usuario
2. Selecciona el nuevo rol
3. Pulsa **Guardar**

> **Nota:** No puedes cambiarte el **propio** rol.

#### Borrar usuario

Pulsa **Borrar** junto al usuario que quieras eliminar. **No puedes borrarte a ti mismo**. Siempre te pedirá confirmación.

## Estados y conceptos

| Concepto | Significado |
|---|---|
| **Pendiente** | Orden/factura/pedido creado pero sin iniciar o sin cobrar/recibir |
| **En curso** | Reparación en proceso |
| **Listo** | Reparación finalizada, lista para facturar |
| **Recibido** | Pedido de recambios recibido y stock actualizado |
| **Cancelado** | Pedido anulado |
| **Pagada** | Factura cobrada |
| **Anulada** | Factura anulada |
| **Stock bajo** | Cuando quedan pocas unidades (<= stock mínimo) |

## Trucos prácticos

- **Flujo ideal de una reparación:** `Crear orden` → `Asignar mecánico` → `En curso` → `Listo` → `Facturar`
- **Al recibir pedido:** Siempre usa **"Marcar recibido"**. Así se actualiza el stock automáticamente sin tener que editar recambios manualmente.
- **Búsqueda rápida:** Usa el buscador de **Clientes** por matrícula para encontrar al cliente directamente.
- **Historial:** Desde `Vehículos` > ficha del vehículo tienes todo el histórico de reparaciones de ese coche.
- **Alertas:** Fíjate en las filas rojas de `Recambios` para saber qué conviene pedir antes de quedarte sin stock.

## Preguntas frecuentes

**¿Quién puede ver todas las órdenes?**  
Los roles con permiso sobre el módulo `órdenes` (Administrador, Jefe de Taller...). Los mecánicos solo ven las que tienen asignadas.

**¿Puedo facturar una orden que aún no está "Listo"?**  
Lo ideal es facturar cuando esté en `Listo`. El sistema permite crear facturas, pero lo correcto es hacerlo una vez finalizada la reparación.

**¿Qué pasa si borro un cliente?**  
Se borran también sus vehículos. Si ese cliente tiene órdenes/facturas asociadas, conviene **no borrarlo** y simplemente mantenerlo en el listado.

**¿Puedo editar una factura ya pagada?**  
Se recomienda evitarlo. Si es necesario, mejor cambiarla a `Anulada` y crear una nueva.

**¿Por qué no veo algún módulo?**  
Porque tu rol no tiene permisos para él. Si crees que deberías verlo, habla con el administrador para que te asigne el módulo/rol correspondiente.