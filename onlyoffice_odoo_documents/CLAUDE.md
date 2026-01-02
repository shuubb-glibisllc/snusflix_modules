# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the **ONLYOFFICE Documents** addon for Odoo, which integrates ONLYOFFICE Docs with Odoo's Documents module. It allows users to edit and collaborate on office files (docx, xlsx, pptx, pdf) within Odoo using ONLYOFFICE editors with real-time collaboration features.

## Architecture

### Core Components

**Models (models/):**
- `onlyoffice_odoo_documents.py` - Main model for document role management and sharing logic
- `documents.py` - Extends `documents.document` with ONLYOFFICE-specific functionality 
- `ir_attachment.py` - Attachment handling extensions
- `onlyoffice_documents_access.py` - Document access control model
- `onlyoffice_documents_access_user.py` - User-specific access permissions

**Controllers (controllers/):**
- `controllers.py` - HTTP routes for document creation, sharing, and editor rendering
  - `OnlyofficeDocuments_Connector` - Document creation endpoints
  - `OnlyofficeDocuments_Inherited_Connector` - Editor rendering and callbacks
  - `OnlyOfficeShareRoute` - Public document sharing

**Frontend (static/):**
- JavaScript models in `static/src/models/`
- XML templates in `static/src/components/` and `static/src/documents_view/`
- Document creation dialogs and inspectors

### Key Features

**Document Permissions System:**
- Role-based access: viewer, commenter, reviewer, editor, form_filling, custom_filter
- File-type specific roles (different roles available for docx/xlsx/pptx/pdf)
- Internal user vs public link sharing
- Advanced sharing with user-specific permissions

**Editor Integration:**
- Direct integration with ONLYOFFICE Docs server
- JWT token authentication support
- Real-time collaboration callbacks
- Mobile and desktop editor modes

## Dependencies

- **Required Odoo modules:** `onlyoffice_odoo`, `documents`
- **External:** ONLYOFFICE Docs server, requests library
- **Build system:** whool (specified in pyproject.toml)

## Development

### File Structure
```
├── models/           # Odoo model extensions and business logic
├── controllers/      # HTTP endpoints and editor integration  
├── static/src/       # Frontend JavaScript/XML components
├── views/            # Odoo view definitions
├── security/         # Access control rules
└── i18n/            # Translations
```

### Model Inheritance Patterns
- Models extend base Odoo models using `_inherit`
- Document model extends `documents.document` 
- Controllers inherit from `onlyoffice_odoo.controllers.controllers.Onlyoffice_Connector`

### Security Model
- Access rights defined in `security/ir.model.access.csv`
- Permission checks in controllers use `check_access_rule()`
- JWT tokens used for secure editor communication

### Editor Integration Flow
1. Document creation via `/onlyoffice/documents/file/create`
2. Editor rendering via `/onlyoffice/editor/document/<id>`
3. Document saving via callback URLs with JWT authentication
4. Public sharing via access tokens

## File Type Support
- **docx** - Word documents (no form_filling/custom_filter roles)
- **xlsx** - Excel spreadsheets (no reviewer/form_filling roles) 
- **pptx** - PowerPoint presentations (no reviewer/form_filling/custom_filter roles)
- **pdf** - PDF files (limited to viewer/editor/form_filling roles)