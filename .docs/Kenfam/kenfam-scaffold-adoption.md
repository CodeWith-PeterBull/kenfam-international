# Kenfam Scaffold Adoption And Source Provenance

## 1. Independent Client, Reusable Capability

Client repository: CodeWith-PeterBull/kenfam-international, private by requirement.
Local application root: C:/Users/Peter Maina/Desktop/projects/kenfam-international.
It is not a child Git repository of Custom Templates Builds.

Aureon source repository: CodeWith-PeterBull/Custom-Templates-Builds.
Source branch: feature/laravel-aureon-base-engine.
Source commit: c7aaddbb40e85dd1796576ce350c8a4a97af0189.
Imported tracked subtree: laravel-aureon.
Independent parentless baseline commit: f4139b7.

The baseline was imported through the Git index without overwriting working draft
files. It contains the original tracked scaffold, not the Kenfam/TravelTours
implementation. The draft is isolated on feature/travel-tours-foundation.
aureon-upstream is fetch-only by a disabled push URL. Source application files
were not changed. Remote visibility/publication must be verified independently;
a local origin URL does not prove the repository exists or has been pushed.

## 2. Preserved Capabilities

Authentication, profile/2FA, roles/permissions, role dashboards, layouts,
institution details, activity/log viewer, media infrastructure, shared mail/PDF
templates, Vite/static-copy tooling and reference documentation remain inherited.
Travel adds module-owned routes/classes/resources, not a replacement dashboard
framework. Do not remove reference code merely to hide navigation.

Commerce and PropertyBooking default disabled. Check all their runtime entry
points, not only config files. Disablement must not delete old tables or uploads.
TravelTours owns generic domain naming; Kenfam owns editorial identity.

## 3. Adoption Checklist

| Area | Required reconciliation | Evidence required |
| --- | --- | --- |
| Git | Clean source baseline, private origin, read-only upstream | SHA/tree/visibility/remote reference checks |
| Metadata | Composer/npm names and descriptions, consistent lock metadata | composer validate; npm lock validation |
| Environment | APP_NAME/URL, mail/institution defaults, module flags | Safe .env.example, no real keys/passwords |
| Brand | Central logos/favicon/social image and theme defaults | Light/dark/mobile asset inspection |
| Institution | Name/contact/office profile via existing resolver | Database profile versus fallback behavior |
| Homepage | Client shell independent of module availability | Enabled/disabled boot and render tests |
| Auth/dashboard | Preserve secure flow, integrate travel role navigation | Permission/2FA/redirect tests |
| Seeder | Separate access initialization and demo users/content | Production-safe default seed test |
| Build | All real Vite entries and static-copy destinations | Clean build and hosted URL checks |
| Documentation | Generic versus client-specific ownership | Linked plan/ledger and staging guide |

## 4. Upstream Maintenance

Fetch upstream changes for review; do not merge the whole monorepo blindly into
this subtree-shaped client repository. Identify the relevant Laravel subtree
diff and adopt reviewed patches with tests. Record source SHA and client commit
for each adoption. Never push client secrets/content to aureon-upstream.

Do not copy source .env, application keys, database files, sessions, uploaded
private documents, vendor or node_modules into Git. Install dependencies from
locks. Test fixtures belong to the client/module and are explicitly opt-in.

## 5. Phase And Commit Discipline

K0 baseline is distinct from adoption. K1 foundation is distinct from service/UI
work. Each accepted phase receives scoped changes, test evidence and an updated
ledger. Do not label read-only placeholder pages as completed management modules.
Current draft defects are in ../TravelTours/travel-tours-foundation-audit.md.
