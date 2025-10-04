# Mikelo - Ice Cream Inventory System

## Architecture Overview

This is a **multi-phase ice cream inventory management system** using PHP Slim Framework 4 with MVC architecture and AdminLTE frontend.

### Key Components
- **Backend**: Slim 4 API (`api/src/`) with Controllers and Models
- **Frontend**: AdminLTE-based HTML pages with vanilla JS + jQuery
- **Database**: MySQL with complex state tracking system
- **Timezone**: Argentina/Buenos_Aires (configured in `api/index.php`)

## Critical Database Schema

The system tracks inventory through a **state-based flow**:
```
productos → movimientos → movimientos_items → estados_items_movimientos
```

**Core Tables**:
- `movimientos_items`: Central inventory tracking with `id_movimientos_items_origen` for traceability
- `estados`: State flow (NUEVO → ENVIADO → RECIBIDO/CANCELADO)
- `contenedores`: Container types with weights (affects total product weight)
- `ubicaciones`: Locations (central depot = ID 1, branches)

## Development Workflow

### API Structure
Routes in `api/index.php` follow **specific ordering** - static routes before parameterized ones:
```php
// ✅ Correct order
$app->get('/envios/productos-disponibles', ...);  // Static first
$app->get('/envios/{id}', ...);                   // Parameter last
```

### Database Connection
Use `getDB()` from `api/comun.php` - configured with PDO exceptions and associative fetch mode.

### Testing
- `api/test_db.php` - Database connectivity test
- `api/tests.http` - HTTP endpoint testing

## Project-Specific Patterns

### State Management
Products flow through states via `estados_items_movimientos`. **Never directly update** `movimientos_items` state - always create new state records.

### Barcode Integration
Frontend supports **barcode scanning** with format parsing:
- First 2 chars: Type (20=units, 21=weight)
- Next 5 chars: Product code (strip leading zeros)
- Last 5 chars: Quantity/weight value

### Container Handling
- **Alta Depósito**: Can assign containers during product entry
- **Envíos**: Containers are read-only, shown as text or "-"
- Total weight = product weight + container weight

### Item Traceability
Use `id_movimientos_items_origen` to link items across movements. Products available for shipping must have:
```sql
WHERE id_movimientos_items_origen IS NULL  -- Original items only
AND NOT EXISTS (SELECT 1 FROM movimientos_items mi2 WHERE mi2.id_movimientos_items_origen = mi.id)  -- Not referenced
```

### File Organization
- Active code in `api/src/Controller/` and `api/src/Model/`
- Legacy/duplicate folders renamed with `-borrar` suffix
- Frontend modules: `index.html` (movements), `alta_deposito.html` (intake), `envios.html` (shipments)

### Error Handling
API uses `responseJson()` helper from `comun.php` for consistent JSON responses. Frontend shows errors via SweetAlert2.

### Authentication Notes
Currently uses placeholder `'usuario_temporal'` - JWT implementation planned for phase 2.

## Quick Commands

```bash
# Test database connection
php api/test_db.php

# Install dependencies
cd api && composer install
```

## Integration Points

- **AdminLTE 3.2**: UI framework with Bootstrap 4
- **SweetAlert2**: User notifications
- **Html5QrcodeScanner**: Barcode scanning
- **PHPSpreadsheet/MPDF**: Export functionality (planned)