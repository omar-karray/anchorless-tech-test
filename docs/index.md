# Welcome to Anchorless Tech Test Documentation

This documentation provides comprehensive information about the Anchorless Visa Application System - a full-stack application built with Laravel 12 and React Router v7.

## 🚀 New to the Project?

**Start here:** [Getting Started Guide](getting-started.md)

Learn how to clone, set up, and run the application in just 5 minutes:

```bash
git clone https://github.com/omar-karray/anchorless-tech-test.git
cd anchorless-tech-test
make app-boot
# → Open http://localhost:5173 and start developing!
```

## 📚 Documentation Sections

### For Developers

- **[Getting Started](getting-started.md)** - Quick setup guide from git clone to running app
- **[Backend Setup](backend-setup.md)** - Detailed Laravel configuration and environment setup
- **[Backend Architecture](backend-architecture.md)** - System design, patterns, and component overview
- **[Frontend Auth](frontend-auth.md)** - Authentication flow with Laravel Sanctum
- **[API Documentation](api-docs.md)** - Complete API reference with examples

### Technical Deep Dives

- **[Data Model](data-model.md)** - Database schema and relationships
- **[Multipart Upload Flow](multipard-upload-direct-to-file-storage.md)** - File upload architecture with MinIO
- **[Frontend Realtime](frontend-realtime.md)** - WebSocket implementation with Laravel Reverb

## 🏗️ System Overview

This application demonstrates a production-ready visa application management system with:

- **Backend:** Laravel 12 with Sanctum authentication, Reverb WebSockets, Horizon queues
- **Frontend:** React Router v7 with server-side rendering
- **Storage:** MinIO S3-compatible object storage
- **Database:** PostgreSQL with comprehensive relationships
- **Real-time:** WebSocket notifications for file processing
- **Infrastructure:** Full Docker Compose setup with Makefile automation

## 💡 Key Features

✨ **Modern File Upload UX**
- Drag & drop support
- Auto-upload on file selection
- Real-time processing notifications
- Per-category loading states

🔒 **Secure & Scalable**
- Cookie-based authentication with Sanctum
- Policy-based authorization
- Async queue processing
- S3-compatible storage

⚡ **Real-time Updates**
- WebSocket events via Laravel Reverb
- Private channels with authentication
- Automatic UI updates

## 🎯 Quick Links

- [System Requirements](getting-started.md#prerequisites)
- [API Endpoints](api-docs.md)
- [Database Schema](data-model.md)
- [WebSocket Events](frontend-realtime.md)
- [Troubleshooting](getting-started.md#troubleshooting-quick-fixes)

---

**Ready to get started?** Head over to the [Getting Started Guide](getting-started.md)!

Explore the sections above for architecture, data models, API endpoints, and implementation details.
