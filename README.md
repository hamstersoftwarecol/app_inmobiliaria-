# App Inmobiliaria

Plataforma para una agencia inmobiliaria de Nueva Jersey: anuncios (propios y de fuentes MLS
autorizadas), agentes, CRM, visitas, ofertas, transacciones y documentos, con aplicación web,
aplicaciones Android/iOS y una API REST central.

| Aplicación | Tecnología | Ruta |
|---|---|---|
| API REST | Laravel 13 · PHP 8.3 · Sanctum · PostgreSQL 16 + PostGIS · Redis | [`apps/api`](apps/api) |
| Web | Next.js 16 · TypeScript · Tailwind 4 · shadcn/ui · TanStack Query · Zustand · RHF + Zod | [`apps/web`](apps/web) |
| Móvil | Flutter 3.47 · Riverpod · GoRouter · Dio · Freezed · almacenamiento seguro | [`apps/mobile`](apps/mobile) |

## Estado: versión candidata 1.0.0-rc.1 (Sprints 1–12)

**Terminado y probado (web + API + móvil):**

- **Seguridad:** inicio de sesión (cookie en la web, tokens en el móvil), verificación en dos pasos
  (TOTP), sesiones y protección contra ataques de fuerza bruta. Roles y permisos (6 roles,
  50 permisos) y registro de auditoría.
- **Agencia:** agencias, oficinas, agentes y verificación de licencias.
- **MLS:** motor MLS (adaptador RESO Web API, normalización, reglas de cumplimiento y monitor de
  sincronización).
- **Anuncios y búsqueda:** anuncios con fotos y flujo de publicación. Búsqueda con filtros, radio,
  área o polígono, orden y autocompletado. Favoritos y alertas de búsquedas guardadas.
- **CRM y visitas:** consultas, embudo de leads, historial, tareas e informes. Visitas con
  disponibilidad, horarios seguros ante el cambio de hora y sin dobles reservas. Centro de
  notificaciones.
- **Operaciones:** ofertas y transacciones con los hitos de Nueva Jersey. Documentos privados con
  versiones y descargas firmadas. Mensajería privada.
- **Informes:** panel de indicadores de la agencia con exportaciones CSV auditadas.

La app Flutter incluye:

- búsqueda y ficha de anuncio, favoritos y búsquedas guardadas;
- visitas, notificaciones y mensajes;
- ofertas, directorio de agentes y ajustes de cuenta;
- un modo agente (leads, tareas, disponibilidad y perfil del agente).

Endurecimiento previo al lanzamiento:

- revisiones de accesibilidad (axe) y escaneo de seguridad ZAP;
- endpoint de disponibilidad (readiness) y script de prueba de carga;
- copias de seguridad y restauración, con un simulacro documentado;
- manuales de operación.

Más detalle en [`CHANGELOG.md`](CHANGELOG.md) y
[`docs/operations/runbooks.md`](docs/operations/runbooks.md).

**Bloqueado por autorizaciones o infraestructura externas (no se simula):**

- conexiones MLS reales (licencias, especificaciones y credenciales);
- mapas (token de Mapbox) y notificaciones push (Firebase);
- Cloudflare R2 y ClamAV desplegado;
- seguimiento de errores (DSN de Sentry);
- correo y hosting de producción;
- pruebas de capacidad y de penetración;
- revisión legal.

Detalles en [`docs/product-backlog.md`](docs/product-backlog.md). Este software no está certificado
para ningún fin legal o regulatorio.

## Inicio rápido

Consulta [`docs/deployment/README.md`](docs/deployment/README.md) para la instalación local (con o
sin Docker), las pruebas y la configuración de producción.

```bash
infrastructure/scripts/check-all.sh   # lint y pruebas de todas las aplicaciones
```

## Documentación

* [Visión general de la arquitectura](docs/architecture/overview.md) · [Componentes](docs/architecture/components.md) · [ADR](docs/architecture/adr)
* [Modelo de datos](docs/database/erd.md) · [Contrato OpenAPI 3.1](docs/api/openapi.yaml)
* [Plan de integración MLS](docs/mls/integration-plan.md) · [Plan de seguridad y matriz de permisos](docs/security/security-plan.md)
* [Backlog del producto](docs/product-backlog.md) · [Despliegue](docs/deployment/README.md) · [Manuales de operación](docs/operations/runbooks.md) · [Accesibilidad](docs/security/accessibility.md) · [Registro de cambios](CHANGELOG.md)

## Reglas del proyecto

- No se guardan secretos en el repositorio.
- No se hace scraping de portales de terceros.
- Los datos MLS solo se usan con licencias firmadas y según las reglas de cada proveedor.
- El backend autoriza cada petición.
- Una funcionalidad solo se da por terminada con pruebas y documentación (ver
  [`AGENTS.md`](AGENTS.md)).
