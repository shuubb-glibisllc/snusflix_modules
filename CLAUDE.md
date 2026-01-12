# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is an **Odoo 18 addons repository** containing multiple e-commerce integration modules and document management extensions. The repository serves as a multi-tenant e-commerce synchronization platform with ONLYOFFICE document collaboration capabilities.

## Repository Structure

The repository contains 5 main Odoo addon modules:

1. **bridge_skeleton** - Core e-commerce bridge framework (base module)
2. **oob** - Opencart-Odoo Bridge (depends on bridge_skeleton)
3. **onlyoffice_odoo** - ONLYOFFICE base integration
4. **onlyoffice_odoo_documents** - ONLYOFFICE Documents module extension
5. **label_zebra_printer** - Zebra printer label printing integration

Additionally:
- `opencart_plugin_code_for_reference/` - Reference PHP code for OpenCart plugin

## Core Architecture Patterns

### Bridge Framework Architecture

The **bridge_skeleton** module implements a plugin-based architecture for e-commerce synchronization:

**Key Components:**
- `connector.instance` model - Configuration hub for e-commerce connections (URL, credentials, sync settings)
- `connector.snippet` transient model - Abstract base for sync operations, uses dynamic method dispatch
- Mapping models - Bidirectional ID mapping between Odoo and e-commerce platforms
- Sync models - Category, Product, Attribute synchronization logic

**Design Pattern:**
```
connector.snippet (base)
    ↓ inherits
oob-specific implementation
    ↓ calls
OpencartWebService API client
    ↓ syncs
Opencart platform
```

**Method Dispatch Pattern:**
The framework uses dynamic method naming for platform-specific implementations:
- `_export_{channel}_specific_template()` - Export product to channel
- `_update_{channel}_specific_template()` - Update product on channel
- `create_{channel}_connector_mapping()` - Create channel-specific mapping
- `sync_ecomm_{oprmodel}()` - Sync specific operation model (product/category/attribute)

This allows bridge_skeleton to remain generic while child modules (like oob) implement channel-specific logic.

### Model Organization

**Models Directory Structure:**
```
models/
├── base/          # Core sync infrastructure (connector_instance, connector_snippet, *_sync.py)
├── core/          # Odoo model extensions (product_template, sale, stock_picking, etc.)
├── mapping/       # Bidirectional mapping models (connector_*_mapping.py)
├── operations/    # Business operations (invoice_order, ship_order)
└── dashboard/     # Analytics and reporting
```

**Core Extensions:**
- Models in `core/` extend standard Odoo models using `_inherit` pattern
- Extensions add sync-related fields and methods to track e-commerce state
- Example: `product.template` gains `connector_template_mapping_ids`, `connector_exported` fields

### OOB (Opencart-Odoo Bridge) Specifics

**Authentication Flow:**
1. Instance stores OpenCart API URL (`user` field) and API key (`pwd` field)
2. `test_opencart_connection()` authenticates via `/api/oob/login` endpoint
3. Receives `api_token` (stored in `session_key` field)
4. Token used for all subsequent API requests

**API Client:**
- `OpencartWebService` class in `oob/models/oobapi/oobapi.py`
- Wraps requests library with OpenCart-specific session handling
- Supports JSON payloads and file uploads
- API routes follow pattern: `index.php?route=api/oob/{resource}`

**Synchronization:**
- Bi-directional sync: Odoo ↔ OpenCart
- Products can sync as templates or variants (configurable via `product_configurable` field)
- Inventory updates controlled by `inventory_sync` field (enable/disable)
- Stock management supports QoH (Quantity on Hand) or Forecast Quantity

### ONLYOFFICE Integration Architecture

**Two-Module Design:**
1. **onlyoffice_odoo** - Base integration for any Odoo attachment
2. **onlyoffice_odoo_documents** - Extends Odoo's Documents module with ONLYOFFICE features

**Integration Flow:**
```
User opens document
    ↓
Controller generates JWT token
    ↓
Renders ONLYOFFICE editor iframe
    ↓
Editor callbacks on save
    ↓
JWT verification + update attachment
```

**Key Features:**
- JWT authentication for editor security
- Real-time collaboration support
- Role-based permissions (viewer, editor, commenter, reviewer, form_filling)
- Public sharing with access tokens
- Document templates support

**Configuration:**
- Settings in Settings > General Settings > ONLYOFFICE
- Requires: Document Server Public URL, JWT Secret
- Optional: Inner URL (for Docker/network isolation), Odoo URL override

## Development

### Running Odoo

This is an Odoo 18 instance typically run via Docker:

```bash
# View logs
docker logs odoo18 2>&1 | tail -100

# Restart Odoo container
docker restart odoo18

# Enter container
docker exec -it odoo18 bash
```

### Module Installation/Upgrade

```bash
# From within Odoo container or with Odoo CLI:
odoo-bin -u bridge_skeleton,oob,onlyoffice_odoo,onlyoffice_odoo_documents,label_zebra_printer -d <database_name>

# Or upgrade single module:
odoo-bin -u oob -d <database_name>
```

### Debugging Sync Issues

**Logging Locations:**
- Sync operations: `odoo.addons.bridge_skeleton.models.base.connector_snippet`
- Product sync: `odoo.addons.bridge_skeleton.models.base.product_sync`
- API requests: `odoo.addons.oob.models.oobapi.oobapi`

**Common Log Patterns:**
```python
_logger.info("Product '%s' (ID: %s) updated successfully in %s", product.name, product.id, channel.upper())
_logger.error("Product '%s' (ID: %s) update failed: %s", product.name, product.id, error_msg)
```

**Sync History:**
- All sync operations logged to `connector.sync.history` model
- Check via Odoo UI: Connector Instance > Sync History tab
- Fields: `status`, `action_on`, `instance_id`, `action`, `error_message`

### Testing Connections

**OpenCart:**
```python
# In Odoo shell or via UI button:
instance = env['connector.instance'].browse(1)
instance.test_opencart_connection()
# Check instance.connection_status (Boolean)
# Check instance.status (String message)
```

**ONLYOFFICE:**
- Navigate to Settings > General Settings > ONLYOFFICE
- Click "Test Connection" or save settings
- Validation checks URL format and JWT configuration

### Working with Mappings

Mappings maintain ID correspondence between Odoo and e-commerce platforms:

```python
# Find mapping
mapping = env['connector.product.mapping'].search([
    ('instance_id', '=', instance_id),
    ('odoo_id', '=', product_id)
])

# Create mapping
env['connector.snippet'].create_odoo_connector_mapping(
    'connector.product.mapping',
    ecomm_id=123,
    odoo_id=456,
    instance_id=1,
    channel='opencart'
)
```

**Mapping Models:**
- `connector.template.mapping` - Product templates
- `connector.product.mapping` - Product variants
- `connector.category.mapping` - Product categories
- `connector.attribute.mapping` - Product attributes
- `connector.partner.mapping` - Customers
- `connector.order.mapping` - Sales orders

### Important Fields and Their Meanings

**connector.instance:**
- `ecomm_type` - Platform type (e.g., 'opencart')
- `connection_status` - Boolean, True if API connection successful
- `credential` - Show/hide credentials tab in UI
- `inventory_sync` - Enable/disable forced inventory updates
- `connector_stock_action` - Stock calculation method ('qoh' or 'fq')
- `avoid_*_duplicity` - Prevent duplicate records during import

**Product Sync States:**
- `connector_exported` - Boolean, product exists on e-commerce
- `need_sync` - Boolean, product requires update
- `connector_template_mapping_ids` - One2many to mapping records

### Synchronization Wizards

Located in `bridge_skeleton/wizard/`:

- `synchronization.wizard` - Main sync UI for products/categories
- `status_synchronization.wizard` - Order status updates
- `api_details_wizard` - View/test API configuration
- `message_wizard` - Display sync results to user

**Sync Operations:**
- Export: Create new records on e-commerce platform
- Update: Modify existing synced records

### Error Handling Patterns

**Empty Error Messages:**
If you see logs like:
```
ERROR odoo.addons.bridge_skeleton.models.base.product_sync: Product 'product.template(1130,)' (ID: 1127) update failed:
```

The error message is likely not being captured properly. Check:
1. Exception handling in sync methods (`_export_*`, `_update_*`)
2. Response parsing from API client
3. `response.get('error', 'Unknown error')` patterns in product_sync.py:44-61

**Proper Error Logging:**
```python
try:
    response = api_call()
except Exception as e:
    _logger.error("Operation failed: %s", str(e))
    return {'status': False, 'error': str(e)}
```

### ONLYOFFICE Editor Integration

**Controller Routes:**
- `/onlyoffice/editor` - Render editor for attachments
- `/onlyoffice/editor/document/<id>` - Open specific document (Documents module)
- `/onlyoffice/file/create` - Create new document
- `/onlyoffice/callback` - Editor save callbacks

**JWT Token Generation:**
```python
from odoo.addons.onlyoffice_odoo.utils import jwt_utils

token = jwt_utils.encode_jwt(
    request.env,
    payload={'user_id': user.id, 'file_url': file_url}
)
```

**File Type Support:**
- docx, xlsx, pptx - Full editing
- pdf - View and form filling
- Document formats defined in `onlyoffice_odoo/static/assets/document_formats/`

## Module Dependencies

**Installation Order:**
1. bridge_skeleton (base)
2. oob (requires bridge_skeleton)
3. onlyoffice_odoo (base, no deps)
4. onlyoffice_odoo_documents (requires onlyoffice_odoo + documents)
5. label_zebra_printer (independent)

**External Python Dependencies:**
- `requests` - HTTP client (oob module)
- `pyjwt` - JWT tokens (onlyoffice modules)

## Security Considerations

**API Credentials:**
- Stored in `connector.instance` model
- `pwd` field is not encrypted (stored as Char/Text)
- Session tokens stored in `session_key` field
- Access controlled via `security/connector_security.xml` groups

**ONLYOFFICE Security:**
- JWT tokens required for editor security
- Secret stored in `ir.config_parameter`
- Access tokens for public sharing
- User permissions checked via `check_access_rule()`

**Mapping Records:**
- Security group: `bridge_skeleton.group_bridge_user`
- Access rights in `security/ir.model.access.csv`

## Common Issues

**Sync Failures:**
1. Check `connection_status` on connector.instance
2. Review logs for API request/response details
3. Verify mapping records exist for updates
4. Check product variants are valid (not orphaned/deleted)

**OpenCart Connection Issues:**
1. Ensure URL ends with `/` in `user` field
2. API key in `pwd` field must match OpenCart configuration
3. OpenCart plugin must be installed and enabled
4. Check API route: `/index.php?route=api/oob/login`

**ONLYOFFICE Issues:**
1. Document Server URL must be accessible from Odoo
2. JWT secret must match between Odoo and Document Server
3. Use Inner URL if Document Server in different network
4. Check certificate verification settings for HTTPS

## File Locations Reference

**Bridge Configuration:**
- Instances: UI > Connector > Instances
- Sync History: UI > Connector > Sync History
- Mappings: UI > Connector > Mappings > [type]

**ONLYOFFICE Configuration:**
- Settings > General Settings > ONLYOFFICE
- Documents: UI > Documents module

**Code Entry Points:**
- Product sync: `bridge_skeleton/models/base/product_sync.py`
- Category sync: `bridge_skeleton/models/base/category_sync.py`
- OpenCart API: `oob/models/oobapi/oobapi.py`
- ONLYOFFICE controllers: `onlyoffice_odoo/controllers/controllers.py`
