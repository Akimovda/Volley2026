# DB Schema (high level)

```mermaid
erDiagram
  USERS {
    bigint id PK
    string email "NOT NULL, unique"
    string role
    string profile_photo_path
    string telegram_id "unique, nullable"
    string vk_id "unique, nullable"
    string vk_email "nullable"
    string yandex_id "unique, nullable"
    string yandex_phone "nullable"
    string yandex_avatar "nullable"
    datetime created_at
    datetime updated_at
  }

  SESSIONS {
    string id PK
    bigint user_id FK
    datetime last_activity
  }

  ACCOUNT_LINK_AUDITS {
    bigint id PK
    bigint user_id FK
    bigint linked_from_user_id FK
    datetime created_at
  }

  ACCOUNT_LINK_CODES {
    bigint id PK
    bigint user_id FK
    bigint consumed_by_user_id FK
    datetime created_at
  }

  ACCOUNT_LINKS {
    bigint id PK
    bigint user_id FK
    datetime created_at
  }

  EVENT_REGISTRATIONS {
    bigint id PK
    bigint user_id FK
    bigint event_id FK
    datetime created_at
  }

  ORGANIZER_REQUESTS {
    bigint id PK
    bigint user_id FK
    bigint reviewed_by FK
    datetime created_at
  }

  EVENTS {
    bigint id PK
    bigint organizer_id FK
    datetime created_at
  }

  ORGANIZER_STAFF {
    bigint id PK
    bigint organizer_id FK
    bigint staff_user_id FK
  }

  USER_CLASSIC_POSITIONS {
    bigint id PK
    bigint user_id FK
  }

  USER_BEACH_ZONES {
    bigint id PK
    bigint user_id FK
  }

  ADMIN_AUDITS {
    bigint id PK
    bigint actor_user_id FK
    string target_type
    string target_id
    datetime created_at
  }

  USERS ||--o{ SESSIONS : "user_id (CASCADE)"
  USERS ||--o{ ACCOUNT_LINK_AUDITS : "user_id (CASCADE)"
  USERS ||--o{ ACCOUNT_LINK_CODES : "user_id (CASCADE)"
  USERS ||--o{ ACCOUNT_LINKS : "user_id (CASCADE)"
  USERS ||--o{ EVENT_REGISTRATIONS : "user_id (CASCADE)"
  USERS ||--o{ ORGANIZER_REQUESTS : "user_id (CASCADE)"
  USERS ||--o{ USER_CLASSIC_POSITIONS : "user_id (CASCADE)"
  USERS ||--o{ USER_BEACH_ZONES : "user_id (CASCADE)"
  USERS ||--o{ ORGANIZER_STAFF : "organizer_id (CASCADE)"
  USERS ||--o{ ORGANIZER_STAFF : "staff_user_id (CASCADE)"
  USERS ||--o{ ADMIN_AUDITS : "actor_user_id (CASCADE)"

  USERS ||--o{ EVENTS : "organizer_id (NO ACTION)"
  USERS ||--o{ ORGANIZER_REQUESTS : "reviewed_by (NO ACTION)"

  USERS ||--o{ ACCOUNT_LINK_AUDITS : "linked_from_user_id (SET NULL)"
  USERS ||--o{ ACCOUNT_LINK_CODES : "consumed_by_user_id (SET NULL)"
