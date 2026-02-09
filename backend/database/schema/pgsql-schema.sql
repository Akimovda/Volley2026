--
-- PostgreSQL database dump
--

\restrict Wclqz4sPeHAePlRONxO22yn78A24bQgSB8GLVpcKPtCSYkNEEco6bOu4YtOhCTD

-- Dumped from database version 16.11 (Ubuntu 16.11-0ubuntu0.24.04.1)
-- Dumped by pg_dump version 16.11 (Ubuntu 16.11-0ubuntu0.24.04.1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: account_delete_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.account_delete_requests (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    status character varying(255) DEFAULT 'new'::character varying NOT NULL,
    reason text,
    notified_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: account_delete_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.account_delete_requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: account_delete_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.account_delete_requests_id_seq OWNED BY public.account_delete_requests.id;


--
-- Name: account_link_audits; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.account_link_audits (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    linked_from_user_id bigint,
    provider character varying(32) NOT NULL,
    provider_user_id character varying(128),
    method character varying(32) DEFAULT 'link_code'::character varying NOT NULL,
    link_code_id bigint,
    ip character varying(64),
    user_agent character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: account_link_audits_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.account_link_audits_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: account_link_audits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.account_link_audits_id_seq OWNED BY public.account_link_audits.id;


--
-- Name: account_link_codes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.account_link_codes (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    code_hash character varying(64) NOT NULL,
    target_provider character varying(255),
    expires_at timestamp(0) without time zone NOT NULL,
    consumed_at timestamp(0) without time zone,
    consumed_by_user_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: account_link_codes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.account_link_codes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: account_link_codes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.account_link_codes_id_seq OWNED BY public.account_link_codes.id;


--
-- Name: account_links; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.account_links (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    provider character varying(255) NOT NULL,
    provider_user_id character varying(255) NOT NULL,
    provider_username character varying(255),
    provider_email character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: account_links_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.account_links_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: account_links_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.account_links_id_seq OWNED BY public.account_links.id;


--
-- Name: admin_audits; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.admin_audits (
    id bigint NOT NULL,
    actor_user_id bigint NOT NULL,
    action character varying(64) NOT NULL,
    target_type character varying(64),
    target_id bigint,
    ip character varying(64),
    user_agent character varying(255),
    meta json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    admin_user_id bigint
);


--
-- Name: admin_audits_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.admin_audits_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: admin_audits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.admin_audits_id_seq OWNED BY public.admin_audits.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cities; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cities (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    name character varying(255),
    region character varying(255)
);


--
-- Name: cities_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cities_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cities_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cities_id_seq OWNED BY public.cities.id;


--
-- Name: event_registrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_registrations (
    id bigint NOT NULL,
    event_id bigint NOT NULL,
    user_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: event_registrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.event_registrations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: event_registrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.event_registrations_id_seq OWNED BY public.event_registrations.id;


--
-- Name: event_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_templates (
    id bigint NOT NULL,
    owner_user_id bigint NOT NULL,
    organizer_id bigint,
    name character varying(255) NOT NULL,
    payload json NOT NULL,
    visibility character varying(20) DEFAULT 'private'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    user_id bigint
);


--
-- Name: event_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.event_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: event_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.event_templates_id_seq OWNED BY public.event_templates.id;


--
-- Name: events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.events (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    requires_personal_data boolean DEFAULT false NOT NULL,
    classic_level_min smallint,
    beach_level_min smallint,
    organizer_id bigint,
    starts_at timestamp with time zone,
    location_id bigint,
    timezone character varying(255) DEFAULT 'UTC'::character varying NOT NULL,
    ends_at timestamp(0) with time zone,
    sport_category character varying(20) DEFAULT 'classic'::character varying NOT NULL,
    event_format character varying(40) DEFAULT 'game'::character varying NOT NULL,
    visibility character varying(20) DEFAULT 'public'::character varying NOT NULL,
    public_token uuid,
    is_recurring boolean DEFAULT false NOT NULL,
    rrule character varying(255),
    is_registrable boolean DEFAULT true NOT NULL,
    is_paid boolean DEFAULT false NOT NULL,
    price_text character varying(255),
    is_private boolean DEFAULT false NOT NULL,
    direction character varying(16) DEFAULT 'classic'::character varying NOT NULL,
    format character varying(32) DEFAULT 'game'::character varying NOT NULL,
    allow_registration boolean DEFAULT true NOT NULL,
    recurrence_rule text
);


--
-- Name: events_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.events_id_seq OWNED BY public.events.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: locations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.locations (
    id bigint NOT NULL,
    organizer_id bigint,
    name character varying(255) NOT NULL,
    address character varying(255),
    city character varying(255),
    timezone character varying(255) DEFAULT 'Europe/Berlin'::character varying NOT NULL,
    note text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    short_text character varying(255),
    long_text text,
    lat numeric(10,7),
    lng numeric(10,7)
);


--
-- Name: locations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.locations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: locations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.locations_id_seq OWNED BY public.locations.id;


--
-- Name: media; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.media (
    id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL,
    uuid uuid,
    collection_name character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    file_name character varying(255) NOT NULL,
    mime_type character varying(255),
    disk character varying(255) NOT NULL,
    conversions_disk character varying(255),
    size bigint NOT NULL,
    manipulations json NOT NULL,
    custom_properties json NOT NULL,
    generated_conversions json NOT NULL,
    responsive_images json NOT NULL,
    order_column integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: media_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.media_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: media_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.media_id_seq OWNED BY public.media.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: organizer_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.organizer_requests (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    user_id bigint NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    message text,
    reviewed_by bigint,
    reviewed_at timestamp(0) without time zone
);


--
-- Name: organizer_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.organizer_requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: organizer_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.organizer_requests_id_seq OWNED BY public.organizer_requests.id;


--
-- Name: organizer_staff; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.organizer_staff (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    organizer_id bigint NOT NULL,
    staff_user_id bigint NOT NULL
);


--
-- Name: organizer_staff_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.organizer_staff_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: organizer_staff_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.organizer_staff_id_seq OWNED BY public.organizer_staff.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name text NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: user_beach_zones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_beach_zones (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    zone smallint NOT NULL,
    is_primary boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: user_beach_zones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_beach_zones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_beach_zones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_beach_zones_id_seq OWNED BY public.user_beach_zones.id;


--
-- Name: user_classic_positions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_classic_positions (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    "position" character varying(255) NOT NULL,
    is_primary boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: user_classic_positions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_classic_positions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_classic_positions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_classic_positions_id_seq OWNED BY public.user_classic_positions.id;


--
-- Name: user_restrictions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_restrictions (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    scope character varying(20) NOT NULL,
    ends_at timestamp(0) without time zone,
    event_ids json,
    reason text,
    created_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: user_restrictions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_restrictions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_restrictions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_restrictions_id_seq OWNED BY public.user_restrictions.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    current_team_id bigint,
    profile_photo_path character varying(2048),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    two_factor_secret text,
    two_factor_recovery_codes text,
    two_factor_confirmed_at timestamp(0) without time zone,
    telegram_id character varying(255),
    telegram_username character varying(255),
    last_name character varying(255),
    first_name character varying(255),
    patronymic character varying(255),
    phone character varying(255),
    phone_verified_at timestamp(0) without time zone,
    vk_id character varying(255),
    vk_email character varying(255),
    classic_level smallint,
    beach_level smallint,
    birth_date date,
    beach_universal boolean DEFAULT false NOT NULL,
    city_id bigint,
    role character varying(255) DEFAULT 'user'::character varying NOT NULL,
    gender character varying(1),
    height_cm smallint,
    deleted_at timestamp(0) without time zone,
    yandex_id character varying(255),
    yandex_email character varying(255),
    yandex_phone character varying(255),
    yandex_avatar character varying(255),
    telegram_phone character varying(64),
    vk_phone character varying(64),
    allow_user_contact boolean DEFAULT true NOT NULL
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: account_delete_requests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_delete_requests ALTER COLUMN id SET DEFAULT nextval('public.account_delete_requests_id_seq'::regclass);


--
-- Name: account_link_audits id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_link_audits ALTER COLUMN id SET DEFAULT nextval('public.account_link_audits_id_seq'::regclass);


--
-- Name: account_link_codes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_link_codes ALTER COLUMN id SET DEFAULT nextval('public.account_link_codes_id_seq'::regclass);


--
-- Name: account_links id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_links ALTER COLUMN id SET DEFAULT nextval('public.account_links_id_seq'::regclass);


--
-- Name: admin_audits id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.admin_audits ALTER COLUMN id SET DEFAULT nextval('public.admin_audits_id_seq'::regclass);


--
-- Name: cities id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities ALTER COLUMN id SET DEFAULT nextval('public.cities_id_seq'::regclass);


--
-- Name: event_registrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_registrations ALTER COLUMN id SET DEFAULT nextval('public.event_registrations_id_seq'::regclass);


--
-- Name: event_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_templates ALTER COLUMN id SET DEFAULT nextval('public.event_templates_id_seq'::regclass);


--
-- Name: events id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events ALTER COLUMN id SET DEFAULT nextval('public.events_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: locations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locations ALTER COLUMN id SET DEFAULT nextval('public.locations_id_seq'::regclass);


--
-- Name: media id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media ALTER COLUMN id SET DEFAULT nextval('public.media_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: organizer_requests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizer_requests ALTER COLUMN id SET DEFAULT nextval('public.organizer_requests_id_seq'::regclass);


--
-- Name: organizer_staff id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizer_staff ALTER COLUMN id SET DEFAULT nextval('public.organizer_staff_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: user_beach_zones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_beach_zones ALTER COLUMN id SET DEFAULT nextval('public.user_beach_zones_id_seq'::regclass);


--
-- Name: user_classic_positions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_classic_positions ALTER COLUMN id SET DEFAULT nextval('public.user_classic_positions_id_seq'::regclass);


--
-- Name: user_restrictions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_restrictions ALTER COLUMN id SET DEFAULT nextval('public.user_restrictions_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: account_delete_requests account_delete_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_delete_requests
    ADD CONSTRAINT account_delete_requests_pkey PRIMARY KEY (id);


--
-- Name: account_link_audits account_link_audits_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_link_audits
    ADD CONSTRAINT account_link_audits_pkey PRIMARY KEY (id);


--
-- Name: account_link_codes account_link_codes_code_hash_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_link_codes
    ADD CONSTRAINT account_link_codes_code_hash_unique UNIQUE (code_hash);


--
-- Name: account_link_codes account_link_codes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_link_codes
    ADD CONSTRAINT account_link_codes_pkey PRIMARY KEY (id);


--
-- Name: account_links account_links_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_links
    ADD CONSTRAINT account_links_pkey PRIMARY KEY (id);


--
-- Name: account_links account_links_provider_provider_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_links
    ADD CONSTRAINT account_links_provider_provider_user_id_unique UNIQUE (provider, provider_user_id);


--
-- Name: admin_audits admin_audits_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.admin_audits
    ADD CONSTRAINT admin_audits_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: cities cities_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities
    ADD CONSTRAINT cities_pkey PRIMARY KEY (id);


--
-- Name: event_registrations event_registrations_event_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_registrations
    ADD CONSTRAINT event_registrations_event_id_user_id_unique UNIQUE (event_id, user_id);


--
-- Name: event_registrations event_registrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_registrations
    ADD CONSTRAINT event_registrations_pkey PRIMARY KEY (id);


--
-- Name: event_templates event_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_templates
    ADD CONSTRAINT event_templates_pkey PRIMARY KEY (id);


--
-- Name: events events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_pkey PRIMARY KEY (id);


--
-- Name: events events_public_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_public_token_unique UNIQUE (public_token);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: locations locations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locations
    ADD CONSTRAINT locations_pkey PRIMARY KEY (id);


--
-- Name: media media_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media
    ADD CONSTRAINT media_pkey PRIMARY KEY (id);


--
-- Name: media media_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media
    ADD CONSTRAINT media_uuid_unique UNIQUE (uuid);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: organizer_requests organizer_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizer_requests
    ADD CONSTRAINT organizer_requests_pkey PRIMARY KEY (id);


--
-- Name: organizer_requests organizer_requests_user_id_status_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizer_requests
    ADD CONSTRAINT organizer_requests_user_id_status_unique UNIQUE (user_id, status);


--
-- Name: organizer_staff organizer_staff_organizer_id_staff_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizer_staff
    ADD CONSTRAINT organizer_staff_organizer_id_staff_user_id_unique UNIQUE (organizer_id, staff_user_id);


--
-- Name: organizer_staff organizer_staff_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizer_staff
    ADD CONSTRAINT organizer_staff_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: user_beach_zones user_beach_zones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_beach_zones
    ADD CONSTRAINT user_beach_zones_pkey PRIMARY KEY (id);


--
-- Name: user_beach_zones user_beach_zones_user_id_zone_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_beach_zones
    ADD CONSTRAINT user_beach_zones_user_id_zone_unique UNIQUE (user_id, zone);


--
-- Name: user_classic_positions user_classic_positions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_classic_positions
    ADD CONSTRAINT user_classic_positions_pkey PRIMARY KEY (id);


--
-- Name: user_classic_positions user_classic_positions_user_id_position_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_classic_positions
    ADD CONSTRAINT user_classic_positions_user_id_position_unique UNIQUE (user_id, "position");


--
-- Name: user_restrictions user_restrictions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_restrictions
    ADD CONSTRAINT user_restrictions_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: users users_telegram_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_telegram_id_unique UNIQUE (telegram_id);


--
-- Name: users users_vk_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_vk_id_unique UNIQUE (vk_id);


--
-- Name: users users_yandex_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_yandex_id_unique UNIQUE (yandex_id);


--
-- Name: account_delete_requests_user_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX account_delete_requests_user_id_status_index ON public.account_delete_requests USING btree (user_id, status);


--
-- Name: account_link_audits_linked_from_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX account_link_audits_linked_from_user_id_index ON public.account_link_audits USING btree (linked_from_user_id);


--
-- Name: account_link_audits_user_id_provider_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX account_link_audits_user_id_provider_index ON public.account_link_audits USING btree (user_id, provider);


--
-- Name: account_link_codes_consumed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX account_link_codes_consumed_at_index ON public.account_link_codes USING btree (consumed_at);


--
-- Name: account_link_codes_user_id_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX account_link_codes_user_id_expires_at_index ON public.account_link_codes USING btree (user_id, expires_at);


--
-- Name: account_links_user_id_provider_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX account_links_user_id_provider_index ON public.account_links USING btree (user_id, provider);


--
-- Name: admin_audits_action_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX admin_audits_action_index ON public.admin_audits USING btree (action);


--
-- Name: admin_audits_actor_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX admin_audits_actor_user_id_index ON public.admin_audits USING btree (actor_user_id);


--
-- Name: admin_audits_admin_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX admin_audits_admin_user_id_index ON public.admin_audits USING btree (admin_user_id);


--
-- Name: admin_audits_target_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX admin_audits_target_id_index ON public.admin_audits USING btree (target_id);


--
-- Name: admin_audits_target_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX admin_audits_target_type_index ON public.admin_audits USING btree (target_type);


--
-- Name: event_templates_organizer_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_templates_organizer_id_index ON public.event_templates USING btree (organizer_id);


--
-- Name: event_templates_owner_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_templates_owner_user_id_index ON public.event_templates USING btree (owner_user_id);


--
-- Name: event_templates_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_templates_user_id_index ON public.event_templates USING btree (user_id);


--
-- Name: event_templates_visibility_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_templates_visibility_index ON public.event_templates USING btree (visibility);


--
-- Name: events_event_format_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_event_format_index ON public.events USING btree (event_format);


--
-- Name: events_is_paid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_is_paid_index ON public.events USING btree (is_paid);


--
-- Name: events_is_registrable_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_is_registrable_index ON public.events USING btree (is_registrable);


--
-- Name: events_location_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_location_id_index ON public.events USING btree (location_id);


--
-- Name: events_organizer_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_organizer_id_index ON public.events USING btree (organizer_id);


--
-- Name: events_sport_category_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_sport_category_index ON public.events USING btree (sport_category);


--
-- Name: events_starts_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_starts_at_index ON public.events USING btree (starts_at);


--
-- Name: events_visibility_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_visibility_index ON public.events USING btree (visibility);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: locations_organizer_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX locations_organizer_id_index ON public.locations USING btree (organizer_id);


--
-- Name: media_model_type_model_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX media_model_type_model_id_index ON public.media USING btree (model_type, model_id);


--
-- Name: media_order_column_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX media_order_column_index ON public.media USING btree (order_column);


--
-- Name: organizer_requests_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX organizer_requests_status_index ON public.organizer_requests USING btree (status);


--
-- Name: organizer_requests_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX organizer_requests_user_id_index ON public.organizer_requests USING btree (user_id);


--
-- Name: personal_access_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: user_restrictions_ends_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_restrictions_ends_at_index ON public.user_restrictions USING btree (ends_at);


--
-- Name: user_restrictions_user_id_scope_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_restrictions_user_id_scope_index ON public.user_restrictions USING btree (user_id, scope);


--
-- Name: users_deleted_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_deleted_at_index ON public.users USING btree (deleted_at);


--
-- Name: users_gender_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_gender_index ON public.users USING btree (gender);


--
-- Name: users_height_cm_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_height_cm_index ON public.users USING btree (height_cm);


--
-- Name: users_role_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_role_index ON public.users USING btree (role);


--
-- Name: account_delete_requests account_delete_requests_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_delete_requests
    ADD CONSTRAINT account_delete_requests_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: account_link_audits account_link_audits_link_code_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_link_audits
    ADD CONSTRAINT account_link_audits_link_code_id_foreign FOREIGN KEY (link_code_id) REFERENCES public.account_link_codes(id) ON DELETE SET NULL;


--
-- Name: account_link_audits account_link_audits_linked_from_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_link_audits
    ADD CONSTRAINT account_link_audits_linked_from_user_id_foreign FOREIGN KEY (linked_from_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: account_link_audits account_link_audits_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_link_audits
    ADD CONSTRAINT account_link_audits_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: account_link_codes account_link_codes_consumed_by_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_link_codes
    ADD CONSTRAINT account_link_codes_consumed_by_user_id_foreign FOREIGN KEY (consumed_by_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: account_link_codes account_link_codes_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_link_codes
    ADD CONSTRAINT account_link_codes_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: account_links account_links_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_links
    ADD CONSTRAINT account_links_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: admin_audits admin_audits_actor_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.admin_audits
    ADD CONSTRAINT admin_audits_actor_user_id_foreign FOREIGN KEY (actor_user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: event_registrations event_registrations_event_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_registrations
    ADD CONSTRAINT event_registrations_event_id_foreign FOREIGN KEY (event_id) REFERENCES public.events(id) ON DELETE CASCADE;


--
-- Name: event_registrations event_registrations_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_registrations
    ADD CONSTRAINT event_registrations_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: event_templates event_templates_organizer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_templates
    ADD CONSTRAINT event_templates_organizer_id_foreign FOREIGN KEY (organizer_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: event_templates event_templates_owner_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_templates
    ADD CONSTRAINT event_templates_owner_user_id_foreign FOREIGN KEY (owner_user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: event_templates event_templates_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_templates
    ADD CONSTRAINT event_templates_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: events events_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id) ON DELETE SET NULL;


--
-- Name: events events_organizer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_organizer_id_foreign FOREIGN KEY (organizer_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: locations locations_organizer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locations
    ADD CONSTRAINT locations_organizer_id_foreign FOREIGN KEY (organizer_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: organizer_requests organizer_requests_reviewed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizer_requests
    ADD CONSTRAINT organizer_requests_reviewed_by_foreign FOREIGN KEY (reviewed_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: organizer_requests organizer_requests_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizer_requests
    ADD CONSTRAINT organizer_requests_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: organizer_staff organizer_staff_organizer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizer_staff
    ADD CONSTRAINT organizer_staff_organizer_id_foreign FOREIGN KEY (organizer_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: organizer_staff organizer_staff_staff_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizer_staff
    ADD CONSTRAINT organizer_staff_staff_user_id_foreign FOREIGN KEY (staff_user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_beach_zones user_beach_zones_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_beach_zones
    ADD CONSTRAINT user_beach_zones_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_classic_positions user_classic_positions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_classic_positions
    ADD CONSTRAINT user_classic_positions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_restrictions user_restrictions_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_restrictions
    ADD CONSTRAINT user_restrictions_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: user_restrictions user_restrictions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_restrictions
    ADD CONSTRAINT user_restrictions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: users users_city_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_city_id_foreign FOREIGN KEY (city_id) REFERENCES public.cities(id);


--
-- PostgreSQL database dump complete
--

\unrestrict Wclqz4sPeHAePlRONxO22yn78A24bQgSB8GLVpcKPtCSYkNEEco6bOu4YtOhCTD

--
-- PostgreSQL database dump
--

\restrict xVoofn3vTLs5REFH5LlY8AIha6wDRBlLr1u02ZF6mcv1p9rRS3iQ2uuODsbE4pE

-- Dumped from database version 16.11 (Ubuntu 16.11-0ubuntu0.24.04.1)
-- Dumped by pg_dump version 16.11 (Ubuntu 16.11-0ubuntu0.24.04.1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_01_04_095238_add_two_factor_columns_to_users_table	1
5	2026_01_04_095246_create_personal_access_tokens_table	1
6	2026_01_04_101044_add_telegram_fields_to_users_table	2
7	2026_01_04_112113_add_profile_fields_to_users_table	3
8	2026_01_04_152221_add_vk_id_to_users_table	4
9	2026_01_04_165335_add_vk_fields_to_users_table	5
10	2026_01_04_xxxxxx_add_vk_fields_to_users_table	6
11	2026_01_04_195100_create_events_table	7
12	2026_01_04_195152_create_event_registrations_table	7
13	2026_01_04_201042_add_requirements_to_events_table	8
14	2026_01_04_201224_add_player_levels_to_users_table	9
15	2026_01_05_134633_add_birth_date_and_beach_universal_to_users_table	10
16	2026_01_05_140337_create_user_classic_positions_table	11
17	2026_01_05_140549_create_user_beach_zones_table	11
18	2026_01_05_165905_create_cities_table	12
19	2026_01_05_170138_add_city_id_to_users_table	12
21	2026_01_05_171952_fix_cities_add_name_and_region	13
22	2026_01_05_172114_fix_users_add_city_id	13
23	2026_01_05_174428_create_organizer_requests_table	14
24	2026_01_05_175326_add_organizer_id_to_events_table	15
25	2026_01_05_175351_create_organizer_staff_table	15
26	2026_01_05_XXXXXX_add_organizer_id_to_events_table	15
27	2026_01_05_181002_fix_organizer_staff_add_columns	16
28	2026_01_05_181546_fix_organizer_requests_add_columns	17
29	2026_01_05_182035_add_role_to_users_table	18
32	2026_01_05_224329_add_gender_and_height_to_users_table	19
33	2026_01_08_170238_create_account_link_codes_table	20
34	2026_01_08_170422_create_account_links_table	20
36	2026_01_08_191514_create_account_link_audits_table	21
37	2026_01_11_075401_add_soft_deletes_to_users_table	22
38	2026_01_11_075734_create_admin_audits_table	22
39	2026_01_11_093000_fix_admin_audits_add_admin_user_id	23
40	2026_01_11_094901_fix_admin_audits_admin_user_id	24
41	2026_01_11_095452_fix_admin_audits_admin_user_id	25
42	2026_01_11_190014_add_yandex_fields_to_users_table	26
43	2026_01_11_203128_add_yandex_id_to_users_table	27
44	2026_01_11_210540_add_yandex_fields_to_users_table	28
45	2026_01_12_100551_fix_user_fk_delete_rules_for_purge	29
46	2026_01_13_000001_add_provider_phones_to_users_table	30
47	2026_01_18_065802_create_media_table	31
48	2026_01_19_193935_create_user_restrictions_table	32
49	2026_01_19_211726_add_starts_at_to_events_table	33
50	2026_01_25_1334_create_account_delete_requests_table	34
51	2026_01_25_2203_add_allow_user_contact_to_users_table	35
52	2026_01_25_210000_create_locations_table	36
53	2026_01_25_210100_alter_events_add_scheduler_fields	36
54	2026_01_25_210200_create_event_registrations_table	36
55	2026_01_25_210200_create_locations_table	36
56	2026_01_25_210210_alter_events_add_scheduler_fields	36
57	2026_01_25_999999_fix_events_timezones	37
58	2026_01_27_120810_add_extended_fields_to_locations_table	38
59	2026_01_27_000001_create_event_templates_table	39
60	2026_01_27_192030_add_user_id_to_event_templates_table	40
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 60, true);


--
-- PostgreSQL database dump complete
--

\unrestrict xVoofn3vTLs5REFH5LlY8AIha6wDRBlLr1u02ZF6mcv1p9rRS3iQ2uuODsbE4pE

