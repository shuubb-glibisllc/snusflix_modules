# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This repository contains three Odoo modules for e-commerce integration and label printing:

1. **bridge_skeleton** - Core bridge module providing base functionality for e-commerce integrations
2. **oob** - OpenCart-Odoo Bridge for bi-directional synchronization with OpenCart
3. **label_zebra_printer** - Direct label printing to Zebra printers

## Architecture

### Module Structure
Each module follows standard Odoo structure:
- `__manifest__.py` - Module configuration and dependencies
- `models/` - Data models and business logic
- `views/` - XML view definitions
- `controllers/` - Web controllers for API endpoints
- `security/` - Access control and security rules
- `static/` - CSS, JS, and other static assets
- `wizard/` - Wizard models for user interactions
- `data/` - Initial data and configuration

### Core Components

**bridge_skeleton module:**
- Base classes for connector instances (`connector.instance`)
- Mapping models for products, categories, customers, orders
- Synchronization history tracking
- Dashboard and reporting functionality
- Server actions for automated tasks

**Dependencies:**
- bridge_skeleton depends on: `delivery`, `account_payment`, `stock_delivery`
- oob depends on: `bridge_skeleton`
- label_zebra_printer depends on: `sale_stock`, `barcodes`

## Development Commands

This is an Odoo-based project. Standard Odoo development practices apply:

### Installation
```bash
# Install modules through Odoo interface or command line
odoo-bin -i bridge_skeleton,oob,label_zebra_printer
```

### Running Odoo
```bash
# Standard Odoo server start
odoo-bin --addons-path=/path/to/modules
```

### Testing
- No specific test framework detected in this codebase
- Use Odoo's built-in testing framework if tests are added
- Test files should be placed in `tests/` directories within each module

## Key Files

### Configuration
- `/bridge_skeleton/__manifest__.py` - Core bridge module manifest
- `/oob/__manifest__.py` - OpenCart bridge configuration  
- `/label_zebra_printer/__manifest__.py` - Zebra printer module config

### Core Models
- `/bridge_skeleton/models/base/connector_instance.py` - Base connector configuration
- `/bridge_skeleton/models/base/product_sync.py` - Product synchronization logic
- `/bridge_skeleton/models/base/category_sync.py` - Category synchronization

### Frontend Assets
- Bridge skeleton includes dashboard JavaScript and Kanban views
- Zebra printer module includes QZ-Tray JavaScript for direct printing

## Module Relationships

```
bridge_skeleton (core)
    ├── oob (OpenCart integration)
    └── label_zebra_printer (standalone printing)
```

The bridge_skeleton provides base functionality that other connector modules extend. The oob module specifically implements OpenCart integration using the bridge framework.