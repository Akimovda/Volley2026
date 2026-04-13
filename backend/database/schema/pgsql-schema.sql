--
-- PostgreSQL database dump
--

\restrict LoKldAp9BBD6zZbRtwTKj0GvUCWSc6cRQY2JNMk8YfCMql8tkgXSipFKNVGXJxJ

-- Dumped from database version 16.13 (Ubuntu 16.13-0ubuntu0.24.04.1)
-- Dumped by pg_dump version 16.13 (Ubuntu 16.13-0ubuntu0.24.04.1)

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
-- Name: pg_trgm; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;


--
-- Name: EXTENSION pg_trgm; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pg_trgm IS 'text similarity measurement and index searching based on trigrams';


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
-- Name: broadcast_recipients; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.broadcast_recipients (
    id bigint NOT NULL,
    broadcast_id bigint NOT NULL,
    user_id bigint NOT NULL,
    user_notification_id bigint,
    status character varying(32) DEFAULT 'pending'::character varying NOT NULL,
    error text,
    meta json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: broadcast_recipients_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.broadcast_recipients_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: broadcast_recipients_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.broadcast_recipients_id_seq OWNED BY public.broadcast_recipients.id;


--
-- Name: broadcasts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.broadcasts (
    id bigint NOT NULL,
    created_by bigint NOT NULL,
    name character varying(255) NOT NULL,
    title character varying(500),
    body text,
    image_url character varying(2048),
    button_text character varying(255),
    button_url character varying(2048),
    filters_json json,
    channels_json json,
    status character varying(32) DEFAULT 'draft'::character varying NOT NULL,
    scheduled_at timestamp(0) without time zone,
    started_at timestamp(0) without time zone,
    sent_at timestamp(0) without time zone,
    meta json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: broadcasts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.broadcasts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: broadcasts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.broadcasts_id_seq OWNED BY public.broadcasts.id;


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
-- Name: channel_bind_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.channel_bind_requests (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    platform character varying(32) NOT NULL,
    token character varying(128) NOT NULL,
    status character varying(32) DEFAULT 'pending'::character varying NOT NULL,
    expires_at timestamp(0) without time zone,
    meta json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: channel_bind_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.channel_bind_requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: channel_bind_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.channel_bind_requests_id_seq OWNED BY public.channel_bind_requests.id;


--
-- Name: cities; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cities (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    name character varying(255),
    region character varying(255),
    country_code character varying(2),
    timezone character varying(64),
    lat numeric(10,7),
    lon numeric(10,7),
    population integer,
    geoname_id bigint
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
-- Name: coupon_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.coupon_templates (
    id bigint NOT NULL,
    organizer_id bigint NOT NULL,
    name character varying(150) NOT NULL,
    description text,
    event_ids json,
    valid_from date,
    valid_until date,
    discount_pct smallint NOT NULL,
    uses_per_coupon smallint DEFAULT '1'::smallint NOT NULL,
    cancel_hours_before smallint DEFAULT '0'::smallint NOT NULL,
    transfer_enabled boolean DEFAULT false NOT NULL,
    issue_limit integer,
    issued_count integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: coupon_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.coupon_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: coupon_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.coupon_templates_id_seq OWNED BY public.coupon_templates.id;


--
-- Name: coupons; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.coupons (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    template_id bigint NOT NULL,
    organizer_id bigint NOT NULL,
    code character varying(32) NOT NULL,
    starts_at date,
    expires_at date,
    uses_total smallint NOT NULL,
    uses_used smallint DEFAULT '0'::smallint NOT NULL,
    uses_remaining smallint NOT NULL,
    status character varying(20) DEFAULT 'active'::character varying NOT NULL,
    issued_by bigint,
    issue_channel character varying(20),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: coupons_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.coupons_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: coupons_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.coupons_id_seq OWNED BY public.coupons.id;


--
-- Name: event_channel_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_channel_messages (
    id bigint NOT NULL,
    event_id bigint NOT NULL,
    occurrence_id bigint,
    channel_id bigint NOT NULL,
    platform character varying(32) NOT NULL,
    external_chat_id character varying(191),
    external_message_id character varying(191),
    notification_type character varying(32) DEFAULT 'registration_open'::character varying NOT NULL,
    last_payload_hash character varying(64),
    sent_at timestamp(0) without time zone,
    last_synced_at timestamp(0) without time zone,
    meta json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: event_channel_messages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.event_channel_messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: event_channel_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.event_channel_messages_id_seq OWNED BY public.event_channel_messages.id;


--
-- Name: event_game_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_game_settings (
    id bigint NOT NULL,
    event_id bigint NOT NULL,
    subtype character varying(10) NOT NULL,
    libero_mode character varying(20),
    min_players smallint,
    max_players smallint,
    allow_girls boolean DEFAULT false NOT NULL,
    girls_max smallint,
    positions jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    gender_policy character varying(32),
    gender_limited_side character varying(16),
    gender_limited_max integer,
    gender_limited_positions jsonb,
    teams_count integer DEFAULT 2 NOT NULL
);


--
-- Name: event_game_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.event_game_settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: event_game_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.event_game_settings_id_seq OWNED BY public.event_game_settings.id;


--
-- Name: event_notification_channels; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_notification_channels (
    id bigint NOT NULL,
    event_id bigint NOT NULL,
    channel_id bigint NOT NULL,
    notification_type character varying(32) DEFAULT 'registration_open'::character varying NOT NULL,
    use_private_link boolean DEFAULT false NOT NULL,
    silent boolean DEFAULT false NOT NULL,
    update_message boolean DEFAULT true NOT NULL,
    include_image boolean DEFAULT true NOT NULL,
    include_registered_list boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: event_notification_channels_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.event_notification_channels_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: event_notification_channels_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.event_notification_channels_id_seq OWNED BY public.event_notification_channels.id;


--
-- Name: event_occurrence_stats; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_occurrence_stats (
    occurrence_id bigint NOT NULL,
    registered_count integer DEFAULT 0 NOT NULL,
    updated_at timestamp(0) without time zone,
    created_at timestamp without time zone
);


--
-- Name: event_occurrences; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_occurrences (
    id bigint NOT NULL,
    event_id bigint NOT NULL,
    starts_at timestamp(0) without time zone NOT NULL,
    timezone character varying(64) DEFAULT 'UTC'::character varying NOT NULL,
    is_cancelled boolean DEFAULT false NOT NULL,
    cancelled_at timestamp(0) without time zone,
    uniq_key character varying(120) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    location_id bigint,
    allow_registration boolean,
    max_players integer,
    classic_level_min integer,
    classic_level_max integer,
    beach_level_min integer,
    beach_level_max integer,
    registration_starts_at timestamp with time zone,
    registration_ends_at timestamp with time zone,
    cancel_self_until timestamp with time zone,
    age_policy character varying(16),
    is_snow boolean,
    remind_registration_enabled boolean DEFAULT true NOT NULL,
    remind_registration_minutes_before integer DEFAULT 600 NOT NULL,
    show_participants boolean DEFAULT true NOT NULL,
    duration_sec integer
);


--
-- Name: COLUMN event_occurrences.duration_sec; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.event_occurrences.duration_sec IS 'Продолжительность конкретного повторения в секундах';


--
-- Name: event_occurrences_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.event_occurrences_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: event_occurrences_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.event_occurrences_id_seq OWNED BY public.event_occurrences.id;


--
-- Name: event_registration_group_invites; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_registration_group_invites (
    id bigint NOT NULL,
    event_id bigint NOT NULL,
    group_key character varying(64) NOT NULL,
    from_user_id bigint NOT NULL,
    to_user_id bigint NOT NULL,
    status character varying(24) DEFAULT 'pending'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    auto_join_after_registration boolean DEFAULT false NOT NULL
);


--
-- Name: event_registration_group_invites_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.event_registration_group_invites_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: event_registration_group_invites_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.event_registration_group_invites_id_seq OWNED BY public.event_registration_group_invites.id;


--
-- Name: event_registrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_registrations (
    id bigint NOT NULL,
    event_id bigint NOT NULL,
    user_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    "position" character varying(32),
    status character varying(24) DEFAULT 'confirmed'::character varying NOT NULL,
    is_cancelled boolean DEFAULT false NOT NULL,
    cancelled_at timestamp(0) without time zone,
    occurrence_id bigint,
    group_key character varying(64),
    payment_status character varying(20),
    payment_id bigint,
    payment_expires_at timestamp(0) without time zone,
    subscription_id bigint,
    subscription_usage_id bigint,
    coupon_id bigint,
    coupon_discount_pct smallint,
    confirmed_at timestamp(0) without time zone,
    auto_booked boolean DEFAULT false NOT NULL
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
-- Name: event_role_slots; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_role_slots (
    id bigint NOT NULL,
    event_id bigint NOT NULL,
    role character varying(50) NOT NULL,
    max_slots integer NOT NULL,
    taken_slots integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: event_role_slots_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.event_role_slots_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: event_role_slots_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.event_role_slots_id_seq OWNED BY public.event_role_slots.id;


--
-- Name: event_team_applications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_team_applications (
    id bigint NOT NULL,
    event_id bigint NOT NULL,
    event_team_id bigint NOT NULL,
    status character varying(32) DEFAULT 'pending'::character varying NOT NULL,
    submitted_by_user_id bigint,
    applied_at timestamp(0) without time zone,
    reviewed_by_user_id bigint,
    reviewed_at timestamp(0) without time zone,
    rejection_reason text,
    decision_comment text,
    meta json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: event_team_applications_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.event_team_applications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: event_team_applications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.event_team_applications_id_seq OWNED BY public.event_team_applications.id;


--
-- Name: event_team_invites; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_team_invites (
    id bigint NOT NULL,
    event_id bigint NOT NULL,
    event_team_id bigint NOT NULL,
    invited_user_id bigint NOT NULL,
    invited_by_user_id bigint,
    team_role character varying(32) DEFAULT 'player'::character varying NOT NULL,
    position_code character varying(32),
    token character varying(120) NOT NULL,
    status character varying(32) DEFAULT 'pending'::character varying NOT NULL,
    expires_at timestamp(0) without time zone,
    accepted_at timestamp(0) without time zone,
    declined_at timestamp(0) without time zone,
    revoked_at timestamp(0) without time zone,
    meta json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: event_team_invites_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.event_team_invites_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: event_team_invites_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.event_team_invites_id_seq OWNED BY public.event_team_invites.id;


--
-- Name: event_team_member_audits; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_team_member_audits (
    id bigint NOT NULL,
    event_team_id bigint NOT NULL,
    user_id bigint,
    action character varying(32) NOT NULL,
    performed_by_user_id bigint,
    old_value json,
    new_value json,
    meta json,
    created_at timestamp(0) without time zone
);


--
-- Name: event_team_member_audits_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.event_team_member_audits_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: event_team_member_audits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.event_team_member_audits_id_seq OWNED BY public.event_team_member_audits.id;


--
-- Name: event_team_members; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_team_members (
    id bigint NOT NULL,
    event_team_id bigint NOT NULL,
    user_id bigint NOT NULL,
    role_code character varying(32) DEFAULT 'player'::character varying NOT NULL,
    confirmation_status character varying(32) DEFAULT 'invited'::character varying NOT NULL,
    position_order smallint,
    invited_by_user_id bigint,
    joined_at timestamp(0) without time zone,
    responded_at timestamp(0) without time zone,
    confirmed_at timestamp(0) without time zone,
    meta json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    team_role character varying(32),
    position_code character varying(32)
);


--
-- Name: event_team_members_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.event_team_members_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: event_team_members_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.event_team_members_id_seq OWNED BY public.event_team_members.id;


--
-- Name: event_teams; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_teams (
    id bigint NOT NULL,
    event_id bigint NOT NULL,
    occurrence_id bigint,
    captain_user_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    team_kind character varying(32) NOT NULL,
    status character varying(32) DEFAULT 'draft'::character varying NOT NULL,
    invite_code character varying(64) NOT NULL,
    is_complete boolean DEFAULT false NOT NULL,
    last_checked_at timestamp(0) without time zone,
    confirmed_at timestamp(0) without time zone,
    meta json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: event_teams_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.event_teams_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: event_teams_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.event_teams_id_seq OWNED BY public.event_teams.id;


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
-- Name: event_tournament_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_tournament_settings (
    id bigint NOT NULL,
    event_id bigint NOT NULL,
    registration_mode character varying(32) DEFAULT 'individual'::character varying NOT NULL,
    team_size_min smallint,
    team_size_max smallint,
    require_libero boolean DEFAULT false NOT NULL,
    max_rating_sum integer,
    allow_reserves boolean DEFAULT false NOT NULL,
    captain_confirms_members boolean DEFAULT true NOT NULL,
    auto_submit_when_ready boolean DEFAULT false NOT NULL,
    seeding_mode character varying(32),
    meta json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    game_scheme character varying(32),
    reserve_players_max smallint,
    total_players_max smallint,
    teams_count smallint DEFAULT '4'::smallint NOT NULL
);


--
-- Name: event_tournament_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.event_tournament_settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: event_tournament_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.event_tournament_settings_id_seq OWNED BY public.event_tournament_settings.id;


--
-- Name: event_trainers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_trainers (
    id bigint NOT NULL,
    event_id bigint NOT NULL,
    user_id bigint NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: event_trainers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.event_trainers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: event_trainers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.event_trainers_id_seq OWNED BY public.event_trainers.id;


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
    visibility character varying(20) DEFAULT 'public'::character varying NOT NULL,
    public_token uuid,
    is_recurring boolean DEFAULT false NOT NULL,
    is_paid boolean DEFAULT false NOT NULL,
    price_text character varying(255),
    is_private boolean DEFAULT false NOT NULL,
    direction character varying(16) DEFAULT 'classic'::character varying NOT NULL,
    format character varying(32) DEFAULT 'game'::character varying NOT NULL,
    allow_registration boolean DEFAULT true NOT NULL,
    recurrence_rule text,
    classic_level_max smallint,
    beach_level_max smallint,
    trainer_user_id bigint,
    registration_starts_at timestamp(0) with time zone,
    registration_ends_at timestamp(0) with time zone,
    cancel_self_until timestamp(0) with time zone,
    age_policy character varying(16) DEFAULT 'any'::character varying NOT NULL,
    is_snow boolean DEFAULT false NOT NULL,
    remind_registration_enabled boolean DEFAULT true NOT NULL,
    remind_registration_minutes_before integer DEFAULT 600 NOT NULL,
    show_participants boolean DEFAULT true NOT NULL,
    description_html text,
    duration_sec integer,
    registration_mode character varying(32) DEFAULT 'single'::character varying NOT NULL,
    tournament_teams_count integer DEFAULT 4 NOT NULL,
    price_minor bigint,
    price_currency character(3),
    child_age_min smallint,
    child_age_max smallint,
    bot_assistant_enabled boolean DEFAULT false NOT NULL,
    bot_assistant_threshold smallint DEFAULT '10'::smallint NOT NULL,
    bot_assistant_max_fill_pct smallint DEFAULT '40'::smallint NOT NULL,
    event_photos json,
    payment_method character varying(20),
    payment_link character varying(255),
    refund_hours_full smallint,
    refund_hours_partial smallint,
    refund_partial_pct smallint
);


--
-- Name: COLUMN events.duration_sec; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.events.duration_sec IS 'Продолжительность события в секундах';


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
-- Name: friendships; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.friendships (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    friend_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: friendships_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.friendships_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: friendships_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.friendships_id_seq OWNED BY public.friendships.id;


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
    note text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    short_text character varying(255),
    long_text text,
    lat numeric(10,7),
    lng numeric(10,7),
    city_id bigint,
    long_text_full text
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
-- Name: max_bindings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.max_bindings (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    token character varying(128) NOT NULL,
    expires_at timestamp(0) without time zone NOT NULL,
    used_at timestamp(0) without time zone,
    max_chat_id character varying(255),
    meta json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: max_bindings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.max_bindings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: max_bindings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.max_bindings_id_seq OWNED BY public.max_bindings.id;


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
-- Name: notification_deliveries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notification_deliveries (
    id bigint NOT NULL,
    event_id bigint,
    occurrence_id bigint,
    user_id bigint,
    type character varying(255) NOT NULL,
    channel character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'queued'::character varying NOT NULL,
    scheduled_at timestamp(0) with time zone,
    sent_at timestamp(0) with time zone,
    dedupe_key character varying(255) NOT NULL,
    payload json,
    error text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: notification_deliveries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notification_deliveries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notification_deliveries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notification_deliveries_id_seq OWNED BY public.notification_deliveries.id;


--
-- Name: notification_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notification_templates (
    id bigint NOT NULL,
    code character varying(100) NOT NULL,
    name character varying(255) NOT NULL,
    channel character varying(32),
    title_template character varying(500),
    body_template text,
    image_url character varying(2048),
    button_text character varying(255),
    button_url_template character varying(2048),
    is_active boolean DEFAULT true NOT NULL,
    meta json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: notification_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notification_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notification_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notification_templates_id_seq OWNED BY public.notification_templates.id;


--
-- Name: occurrence_waitlist; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.occurrence_waitlist (
    id bigint NOT NULL,
    occurrence_id bigint NOT NULL,
    user_id bigint NOT NULL,
    positions json DEFAULT '[]'::json NOT NULL,
    notified_at timestamp(0) without time zone,
    notification_expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: occurrence_waitlist_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.occurrence_waitlist_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: occurrence_waitlist_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.occurrence_waitlist_id_seq OWNED BY public.occurrence_waitlist.id;


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
-- Name: page_views; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.page_views (
    id bigint NOT NULL,
    entity_type character varying(50) NOT NULL,
    entity_id bigint NOT NULL,
    user_id bigint,
    ip character varying(45),
    session_id character varying(100),
    is_bot boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: page_views_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.page_views_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: page_views_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.page_views_id_seq OWNED BY public.page_views.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: payment_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payment_settings (
    id bigint NOT NULL,
    organizer_id bigint NOT NULL,
    default_method character varying(20) DEFAULT 'cash'::character varying NOT NULL,
    tbank_link character varying(255),
    sber_link character varying(255),
    yoomoney_shop_id character varying(255),
    yoomoney_secret_key character varying(255),
    yoomoney_enabled boolean DEFAULT false NOT NULL,
    yoomoney_verified boolean DEFAULT false NOT NULL,
    refund_hours_full smallint DEFAULT '48'::smallint NOT NULL,
    refund_hours_partial smallint DEFAULT '24'::smallint NOT NULL,
    refund_partial_pct smallint DEFAULT '50'::smallint NOT NULL,
    refund_no_quorum_full boolean DEFAULT true NOT NULL,
    payment_hold_minutes smallint DEFAULT '15'::smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: payment_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payment_settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payment_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payment_settings_id_seq OWNED BY public.payment_settings.id;


--
-- Name: payments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payments (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    organizer_id bigint,
    event_id bigint,
    occurrence_id bigint,
    registration_id bigint,
    method character varying(20) NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    amount_minor integer NOT NULL,
    currency character varying(3) DEFAULT 'RUB'::character varying NOT NULL,
    yoomoney_payment_id character varying(255),
    yoomoney_confirmation_url character varying(500),
    yoomoney_meta json,
    expires_at timestamp(0) without time zone,
    user_confirmed boolean DEFAULT false NOT NULL,
    org_confirmed boolean DEFAULT false NOT NULL,
    user_confirmed_at timestamp(0) without time zone,
    org_confirmed_at timestamp(0) without time zone,
    refund_amount_minor integer,
    refund_reason character varying(255),
    refunded_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: payments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payments_id_seq OWNED BY public.payments.id;


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
-- Name: platform_payment_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.platform_payment_settings (
    id bigint NOT NULL,
    method character varying(255) DEFAULT 'tbank_link'::character varying NOT NULL,
    tbank_link character varying(255),
    sber_link character varying(255),
    yoomoney_shop_id character varying(255),
    yoomoney_secret_key text,
    yoomoney_verified boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT platform_payment_settings_method_check CHECK (((method)::text = ANY ((ARRAY['tbank_link'::character varying, 'sber_link'::character varying, 'yoomoney'::character varying])::text[])))
);


--
-- Name: platform_payment_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.platform_payment_settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: platform_payment_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.platform_payment_settings_id_seq OWNED BY public.platform_payment_settings.id;


--
-- Name: premium_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.premium_subscriptions (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    plan character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    starts_at timestamp(0) without time zone NOT NULL,
    expires_at timestamp(0) without time zone NOT NULL,
    referred_by bigint,
    payment_id character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    weekly_digest boolean DEFAULT true NOT NULL,
    notify_level_min smallint,
    notify_level_max smallint,
    notify_city_id bigint,
    CONSTRAINT premium_subscriptions_plan_check CHECK (((plan)::text = ANY ((ARRAY['trial'::character varying, 'month'::character varying, 'quarter'::character varying, 'year'::character varying])::text[]))),
    CONSTRAINT premium_subscriptions_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'expired'::character varying, 'cancelled'::character varying, 'pending'::character varying])::text[])))
);


--
-- Name: premium_subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.premium_subscriptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: premium_subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.premium_subscriptions_id_seq OWNED BY public.premium_subscriptions.id;


--
-- Name: profile_visits; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.profile_visits (
    id bigint NOT NULL,
    profile_user_id bigint NOT NULL,
    visitor_user_id bigint NOT NULL,
    visited_at timestamp(0) without time zone NOT NULL
);


--
-- Name: profile_visits_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.profile_visits_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: profile_visits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.profile_visits_id_seq OWNED BY public.profile_visits.id;


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
-- Name: staff_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.staff_logs (
    id bigint NOT NULL,
    staff_user_id bigint NOT NULL,
    organizer_id bigint NOT NULL,
    action character varying(100) NOT NULL,
    entity_type character varying(50),
    entity_id bigint,
    description text,
    meta json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: staff_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.staff_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: staff_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.staff_logs_id_seq OWNED BY public.staff_logs.id;


--
-- Name: subscription_coupon_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subscription_coupon_logs (
    id bigint NOT NULL,
    entity_type character varying(20) NOT NULL,
    entity_id bigint NOT NULL,
    user_id bigint,
    action character varying(30) NOT NULL,
    payload json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: subscription_coupon_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.subscription_coupon_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: subscription_coupon_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.subscription_coupon_logs_id_seq OWNED BY public.subscription_coupon_logs.id;


--
-- Name: subscription_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subscription_templates (
    id bigint NOT NULL,
    organizer_id bigint NOT NULL,
    name character varying(150) NOT NULL,
    description text,
    event_ids json,
    valid_from date,
    valid_until date,
    visits_total smallint NOT NULL,
    cancel_hours_before smallint DEFAULT '0'::smallint NOT NULL,
    freeze_enabled boolean DEFAULT false NOT NULL,
    freeze_max_weeks smallint DEFAULT '0'::smallint NOT NULL,
    freeze_max_months smallint DEFAULT '0'::smallint NOT NULL,
    transfer_enabled boolean DEFAULT false NOT NULL,
    auto_booking_enabled boolean DEFAULT false NOT NULL,
    price_minor integer DEFAULT 0 NOT NULL,
    currency character varying(3) DEFAULT 'RUB'::character varying NOT NULL,
    sale_limit integer,
    sold_count integer DEFAULT 0 NOT NULL,
    sale_enabled boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    duration_months smallint DEFAULT '0'::smallint NOT NULL,
    duration_days smallint DEFAULT '0'::smallint NOT NULL
);


--
-- Name: subscription_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.subscription_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: subscription_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.subscription_templates_id_seq OWNED BY public.subscription_templates.id;


--
-- Name: subscription_usages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subscription_usages (
    id bigint NOT NULL,
    subscription_id bigint NOT NULL,
    user_id bigint NOT NULL,
    event_id bigint NOT NULL,
    occurrence_id bigint NOT NULL,
    registration_id bigint,
    action character varying(20) NOT NULL,
    used_at timestamp(0) without time zone NOT NULL,
    returned_at timestamp(0) without time zone,
    note character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: subscription_usages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.subscription_usages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: subscription_usages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.subscription_usages_id_seq OWNED BY public.subscription_usages.id;


--
-- Name: subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subscriptions (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    template_id bigint NOT NULL,
    organizer_id bigint NOT NULL,
    starts_at date NOT NULL,
    expires_at date,
    visits_total smallint NOT NULL,
    visits_used smallint DEFAULT '0'::smallint NOT NULL,
    visits_remaining smallint NOT NULL,
    status character varying(20) DEFAULT 'active'::character varying NOT NULL,
    frozen_at date,
    frozen_until date,
    auto_booking boolean DEFAULT false NOT NULL,
    auto_booking_event_ids json,
    payment_id bigint,
    payment_status character varying(20),
    issued_by bigint,
    issue_reason character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.subscriptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.subscriptions_id_seq OWNED BY public.subscriptions.id;


--
-- Name: telegram_notify_bindings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.telegram_notify_bindings (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    token character varying(255) NOT NULL,
    telegram_chat_id character varying(255),
    completed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    used_at timestamp(0) without time zone,
    raw_update jsonb
);


--
-- Name: telegram_notify_bindings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.telegram_notify_bindings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: telegram_notify_bindings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.telegram_notify_bindings_id_seq OWNED BY public.telegram_notify_bindings.id;


--
-- Name: test_migration_probe; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.test_migration_probe (
    id bigint
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
-- Name: user_level_votes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_level_votes (
    id bigint NOT NULL,
    voter_id bigint NOT NULL,
    target_id bigint NOT NULL,
    direction character varying(10) NOT NULL,
    level smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: user_level_votes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_level_votes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_level_votes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_level_votes_id_seq OWNED BY public.user_level_votes.id;


--
-- Name: user_notification_channels; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_notification_channels (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    platform character varying(32) NOT NULL,
    title character varying(255),
    chat_id character varying(191) NOT NULL,
    is_verified boolean DEFAULT false NOT NULL,
    meta json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    verified_at timestamp(0) without time zone
);


--
-- Name: user_notification_channels_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_notification_channels_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_notification_channels_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_notification_channels_id_seq OWNED BY public.user_notification_channels.id;


--
-- Name: user_notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_notifications (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    type character varying(64) NOT NULL,
    title character varying(255) NOT NULL,
    body text,
    payload jsonb,
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: user_notifications_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_notifications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_notifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_notifications_id_seq OWNED BY public.user_notifications.id;


--
-- Name: user_play_likes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_play_likes (
    id bigint NOT NULL,
    liker_id bigint NOT NULL,
    target_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: user_play_likes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_play_likes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_play_likes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_play_likes_id_seq OWNED BY public.user_play_likes.id;


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
    allow_user_contact boolean DEFAULT true NOT NULL,
    max_chat_id character varying(255),
    max_linked_at timestamp(0) without time zone,
    max_notifications_enabled boolean DEFAULT false NOT NULL,
    avatar_media_id bigint,
    telegram_notify_chat_id character varying(255),
    telegram_notify_linked_at timestamp(0) without time zone,
    vk_notify_user_id character varying(255),
    vk_notify_linked_at timestamp(0) without time zone,
    is_bot boolean DEFAULT false NOT NULL,
    hide_age boolean DEFAULT false NOT NULL
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
-- Name: virtual_wallets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.virtual_wallets (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    organizer_id bigint NOT NULL,
    balance_minor integer DEFAULT 0 NOT NULL,
    currency character varying(3) DEFAULT 'RUB'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: virtual_wallets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.virtual_wallets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: virtual_wallets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.virtual_wallets_id_seq OWNED BY public.virtual_wallets.id;


--
-- Name: vk_notify_bindings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vk_notify_bindings (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    token character varying(255) NOT NULL,
    vk_user_id character varying(255),
    completed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    used_at timestamp(0) without time zone,
    raw_update jsonb
);


--
-- Name: vk_notify_bindings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vk_notify_bindings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vk_notify_bindings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vk_notify_bindings_id_seq OWNED BY public.vk_notify_bindings.id;


--
-- Name: volleyball_schools; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.volleyball_schools (
    id bigint NOT NULL,
    organizer_id bigint NOT NULL,
    slug character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    direction character varying(20) DEFAULT 'classic'::character varying NOT NULL,
    description text,
    city character varying(255),
    phone character varying(255),
    email character varying(255),
    website character varying(255),
    is_published boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    vk_url character varying(255),
    tg_url character varying(255),
    max_url character varying(255),
    city_id bigint,
    logo_media_id bigint,
    cover_media_id bigint
);


--
-- Name: volleyball_schools_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.volleyball_schools_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: volleyball_schools_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.volleyball_schools_id_seq OWNED BY public.volleyball_schools.id;


--
-- Name: wallet_transactions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wallet_transactions (
    id bigint NOT NULL,
    wallet_id bigint NOT NULL,
    type character varying(20) NOT NULL,
    amount_minor integer NOT NULL,
    currency character varying(3) DEFAULT 'RUB'::character varying NOT NULL,
    reason character varying(50),
    event_id bigint,
    occurrence_id bigint,
    payment_id bigint,
    description character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: wallet_transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.wallet_transactions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wallet_transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wallet_transactions_id_seq OWNED BY public.wallet_transactions.id;


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
-- Name: broadcast_recipients id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.broadcast_recipients ALTER COLUMN id SET DEFAULT nextval('public.broadcast_recipients_id_seq'::regclass);


--
-- Name: broadcasts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.broadcasts ALTER COLUMN id SET DEFAULT nextval('public.broadcasts_id_seq'::regclass);


--
-- Name: channel_bind_requests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.channel_bind_requests ALTER COLUMN id SET DEFAULT nextval('public.channel_bind_requests_id_seq'::regclass);


--
-- Name: cities id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities ALTER COLUMN id SET DEFAULT nextval('public.cities_id_seq'::regclass);


--
-- Name: coupon_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.coupon_templates ALTER COLUMN id SET DEFAULT nextval('public.coupon_templates_id_seq'::regclass);


--
-- Name: coupons id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.coupons ALTER COLUMN id SET DEFAULT nextval('public.coupons_id_seq'::regclass);


--
-- Name: event_channel_messages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_channel_messages ALTER COLUMN id SET DEFAULT nextval('public.event_channel_messages_id_seq'::regclass);


--
-- Name: event_game_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_game_settings ALTER COLUMN id SET DEFAULT nextval('public.event_game_settings_id_seq'::regclass);


--
-- Name: event_notification_channels id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_notification_channels ALTER COLUMN id SET DEFAULT nextval('public.event_notification_channels_id_seq'::regclass);


--
-- Name: event_occurrences id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_occurrences ALTER COLUMN id SET DEFAULT nextval('public.event_occurrences_id_seq'::regclass);


--
-- Name: event_registration_group_invites id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_registration_group_invites ALTER COLUMN id SET DEFAULT nextval('public.event_registration_group_invites_id_seq'::regclass);


--
-- Name: event_registrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_registrations ALTER COLUMN id SET DEFAULT nextval('public.event_registrations_id_seq'::regclass);


--
-- Name: event_role_slots id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_role_slots ALTER COLUMN id SET DEFAULT nextval('public.event_role_slots_id_seq'::regclass);


--
-- Name: event_team_applications id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_applications ALTER COLUMN id SET DEFAULT nextval('public.event_team_applications_id_seq'::regclass);


--
-- Name: event_team_invites id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_invites ALTER COLUMN id SET DEFAULT nextval('public.event_team_invites_id_seq'::regclass);


--
-- Name: event_team_member_audits id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_member_audits ALTER COLUMN id SET DEFAULT nextval('public.event_team_member_audits_id_seq'::regclass);


--
-- Name: event_team_members id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_members ALTER COLUMN id SET DEFAULT nextval('public.event_team_members_id_seq'::regclass);


--
-- Name: event_teams id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_teams ALTER COLUMN id SET DEFAULT nextval('public.event_teams_id_seq'::regclass);


--
-- Name: event_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_templates ALTER COLUMN id SET DEFAULT nextval('public.event_templates_id_seq'::regclass);


--
-- Name: event_tournament_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_tournament_settings ALTER COLUMN id SET DEFAULT nextval('public.event_tournament_settings_id_seq'::regclass);


--
-- Name: event_trainers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_trainers ALTER COLUMN id SET DEFAULT nextval('public.event_trainers_id_seq'::regclass);


--
-- Name: events id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events ALTER COLUMN id SET DEFAULT nextval('public.events_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: friendships id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.friendships ALTER COLUMN id SET DEFAULT nextval('public.friendships_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: locations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locations ALTER COLUMN id SET DEFAULT nextval('public.locations_id_seq'::regclass);


--
-- Name: max_bindings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.max_bindings ALTER COLUMN id SET DEFAULT nextval('public.max_bindings_id_seq'::regclass);


--
-- Name: media id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media ALTER COLUMN id SET DEFAULT nextval('public.media_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: notification_deliveries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_deliveries ALTER COLUMN id SET DEFAULT nextval('public.notification_deliveries_id_seq'::regclass);


--
-- Name: notification_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_templates ALTER COLUMN id SET DEFAULT nextval('public.notification_templates_id_seq'::regclass);


--
-- Name: occurrence_waitlist id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrence_waitlist ALTER COLUMN id SET DEFAULT nextval('public.occurrence_waitlist_id_seq'::regclass);


--
-- Name: organizer_requests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizer_requests ALTER COLUMN id SET DEFAULT nextval('public.organizer_requests_id_seq'::regclass);


--
-- Name: organizer_staff id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizer_staff ALTER COLUMN id SET DEFAULT nextval('public.organizer_staff_id_seq'::regclass);


--
-- Name: page_views id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.page_views ALTER COLUMN id SET DEFAULT nextval('public.page_views_id_seq'::regclass);


--
-- Name: payment_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_settings ALTER COLUMN id SET DEFAULT nextval('public.payment_settings_id_seq'::regclass);


--
-- Name: payments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments ALTER COLUMN id SET DEFAULT nextval('public.payments_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: platform_payment_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_payment_settings ALTER COLUMN id SET DEFAULT nextval('public.platform_payment_settings_id_seq'::regclass);


--
-- Name: premium_subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.premium_subscriptions ALTER COLUMN id SET DEFAULT nextval('public.premium_subscriptions_id_seq'::regclass);


--
-- Name: profile_visits id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.profile_visits ALTER COLUMN id SET DEFAULT nextval('public.profile_visits_id_seq'::regclass);


--
-- Name: staff_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff_logs ALTER COLUMN id SET DEFAULT nextval('public.staff_logs_id_seq'::regclass);


--
-- Name: subscription_coupon_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_coupon_logs ALTER COLUMN id SET DEFAULT nextval('public.subscription_coupon_logs_id_seq'::regclass);


--
-- Name: subscription_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_templates ALTER COLUMN id SET DEFAULT nextval('public.subscription_templates_id_seq'::regclass);


--
-- Name: subscription_usages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_usages ALTER COLUMN id SET DEFAULT nextval('public.subscription_usages_id_seq'::regclass);


--
-- Name: subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions ALTER COLUMN id SET DEFAULT nextval('public.subscriptions_id_seq'::regclass);


--
-- Name: telegram_notify_bindings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_notify_bindings ALTER COLUMN id SET DEFAULT nextval('public.telegram_notify_bindings_id_seq'::regclass);


--
-- Name: user_beach_zones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_beach_zones ALTER COLUMN id SET DEFAULT nextval('public.user_beach_zones_id_seq'::regclass);


--
-- Name: user_classic_positions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_classic_positions ALTER COLUMN id SET DEFAULT nextval('public.user_classic_positions_id_seq'::regclass);


--
-- Name: user_level_votes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_level_votes ALTER COLUMN id SET DEFAULT nextval('public.user_level_votes_id_seq'::regclass);


--
-- Name: user_notification_channels id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_notification_channels ALTER COLUMN id SET DEFAULT nextval('public.user_notification_channels_id_seq'::regclass);


--
-- Name: user_notifications id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_notifications ALTER COLUMN id SET DEFAULT nextval('public.user_notifications_id_seq'::regclass);


--
-- Name: user_play_likes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_play_likes ALTER COLUMN id SET DEFAULT nextval('public.user_play_likes_id_seq'::regclass);


--
-- Name: user_restrictions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_restrictions ALTER COLUMN id SET DEFAULT nextval('public.user_restrictions_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: virtual_wallets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.virtual_wallets ALTER COLUMN id SET DEFAULT nextval('public.virtual_wallets_id_seq'::regclass);


--
-- Name: vk_notify_bindings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vk_notify_bindings ALTER COLUMN id SET DEFAULT nextval('public.vk_notify_bindings_id_seq'::regclass);


--
-- Name: volleyball_schools id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.volleyball_schools ALTER COLUMN id SET DEFAULT nextval('public.volleyball_schools_id_seq'::regclass);


--
-- Name: wallet_transactions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wallet_transactions ALTER COLUMN id SET DEFAULT nextval('public.wallet_transactions_id_seq'::regclass);


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
-- Name: broadcast_recipients broadcast_recipients_broadcast_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.broadcast_recipients
    ADD CONSTRAINT broadcast_recipients_broadcast_id_user_id_unique UNIQUE (broadcast_id, user_id);


--
-- Name: broadcast_recipients broadcast_recipients_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.broadcast_recipients
    ADD CONSTRAINT broadcast_recipients_pkey PRIMARY KEY (id);


--
-- Name: broadcasts broadcasts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.broadcasts
    ADD CONSTRAINT broadcasts_pkey PRIMARY KEY (id);


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
-- Name: channel_bind_requests channel_bind_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.channel_bind_requests
    ADD CONSTRAINT channel_bind_requests_pkey PRIMARY KEY (id);


--
-- Name: channel_bind_requests channel_bind_requests_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.channel_bind_requests
    ADD CONSTRAINT channel_bind_requests_token_unique UNIQUE (token);


--
-- Name: cities cities_geoname_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities
    ADD CONSTRAINT cities_geoname_id_unique UNIQUE (geoname_id);


--
-- Name: cities cities_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities
    ADD CONSTRAINT cities_pkey PRIMARY KEY (id);


--
-- Name: coupon_templates coupon_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.coupon_templates
    ADD CONSTRAINT coupon_templates_pkey PRIMARY KEY (id);


--
-- Name: coupons coupons_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.coupons
    ADD CONSTRAINT coupons_code_unique UNIQUE (code);


--
-- Name: coupons coupons_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.coupons
    ADD CONSTRAINT coupons_pkey PRIMARY KEY (id);


--
-- Name: event_registration_group_invites ergi_event_group_user_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_registration_group_invites
    ADD CONSTRAINT ergi_event_group_user_unique UNIQUE (event_id, group_key, to_user_id);


--
-- Name: event_channel_messages event_channel_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_channel_messages
    ADD CONSTRAINT event_channel_messages_pkey PRIMARY KEY (id);


--
-- Name: event_game_settings event_game_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_game_settings
    ADD CONSTRAINT event_game_settings_pkey PRIMARY KEY (id);


--
-- Name: event_notification_channels event_notification_channels_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_notification_channels
    ADD CONSTRAINT event_notification_channels_pkey PRIMARY KEY (id);


--
-- Name: event_occurrence_stats event_occurrence_stats_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_occurrence_stats
    ADD CONSTRAINT event_occurrence_stats_pkey PRIMARY KEY (occurrence_id);


--
-- Name: event_occurrences event_occurrences_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_occurrences
    ADD CONSTRAINT event_occurrences_pkey PRIMARY KEY (id);


--
-- Name: event_occurrences event_occurrences_uniq_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_occurrences
    ADD CONSTRAINT event_occurrences_uniq_key_unique UNIQUE (uniq_key);


--
-- Name: event_registration_group_invites event_registration_group_invites_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_registration_group_invites
    ADD CONSTRAINT event_registration_group_invites_pkey PRIMARY KEY (id);


--
-- Name: event_registrations event_registrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_registrations
    ADD CONSTRAINT event_registrations_pkey PRIMARY KEY (id);


--
-- Name: event_role_slots event_role_slots_event_id_role_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_role_slots
    ADD CONSTRAINT event_role_slots_event_id_role_unique UNIQUE (event_id, role);


--
-- Name: event_role_slots event_role_slots_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_role_slots
    ADD CONSTRAINT event_role_slots_pkey PRIMARY KEY (id);


--
-- Name: event_team_applications event_team_applications_event_team_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_applications
    ADD CONSTRAINT event_team_applications_event_team_id_unique UNIQUE (event_team_id);


--
-- Name: event_team_applications event_team_applications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_applications
    ADD CONSTRAINT event_team_applications_pkey PRIMARY KEY (id);


--
-- Name: event_team_invites event_team_invites_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_invites
    ADD CONSTRAINT event_team_invites_pkey PRIMARY KEY (id);


--
-- Name: event_team_invites event_team_invites_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_invites
    ADD CONSTRAINT event_team_invites_token_unique UNIQUE (token);


--
-- Name: event_team_member_audits event_team_member_audits_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_member_audits
    ADD CONSTRAINT event_team_member_audits_pkey PRIMARY KEY (id);


--
-- Name: event_team_members event_team_members_event_team_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_members
    ADD CONSTRAINT event_team_members_event_team_id_user_id_unique UNIQUE (event_team_id, user_id);


--
-- Name: event_team_members event_team_members_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_members
    ADD CONSTRAINT event_team_members_pkey PRIMARY KEY (id);


--
-- Name: event_teams event_teams_event_id_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_teams
    ADD CONSTRAINT event_teams_event_id_name_unique UNIQUE (event_id, name);


--
-- Name: event_teams event_teams_invite_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_teams
    ADD CONSTRAINT event_teams_invite_code_unique UNIQUE (invite_code);


--
-- Name: event_teams event_teams_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_teams
    ADD CONSTRAINT event_teams_pkey PRIMARY KEY (id);


--
-- Name: event_templates event_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_templates
    ADD CONSTRAINT event_templates_pkey PRIMARY KEY (id);


--
-- Name: event_tournament_settings event_tournament_settings_event_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_tournament_settings
    ADD CONSTRAINT event_tournament_settings_event_id_unique UNIQUE (event_id);


--
-- Name: event_tournament_settings event_tournament_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_tournament_settings
    ADD CONSTRAINT event_tournament_settings_pkey PRIMARY KEY (id);


--
-- Name: event_trainers event_trainers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_trainers
    ADD CONSTRAINT event_trainers_pkey PRIMARY KEY (id);


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
-- Name: friendships friendships_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.friendships
    ADD CONSTRAINT friendships_pkey PRIMARY KEY (id);


--
-- Name: friendships friendships_user_id_friend_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.friendships
    ADD CONSTRAINT friendships_user_id_friend_id_unique UNIQUE (user_id, friend_id);


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
-- Name: max_bindings max_bindings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.max_bindings
    ADD CONSTRAINT max_bindings_pkey PRIMARY KEY (id);


--
-- Name: max_bindings max_bindings_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.max_bindings
    ADD CONSTRAINT max_bindings_token_unique UNIQUE (token);


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
-- Name: notification_deliveries notification_deliveries_dedupe_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_deliveries
    ADD CONSTRAINT notification_deliveries_dedupe_key_unique UNIQUE (dedupe_key);


--
-- Name: notification_deliveries notification_deliveries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_deliveries
    ADD CONSTRAINT notification_deliveries_pkey PRIMARY KEY (id);


--
-- Name: notification_templates notification_templates_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_templates
    ADD CONSTRAINT notification_templates_code_unique UNIQUE (code);


--
-- Name: notification_templates notification_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_templates
    ADD CONSTRAINT notification_templates_pkey PRIMARY KEY (id);


--
-- Name: occurrence_waitlist occurrence_waitlist_occurrence_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrence_waitlist
    ADD CONSTRAINT occurrence_waitlist_occurrence_id_user_id_unique UNIQUE (occurrence_id, user_id);


--
-- Name: occurrence_waitlist occurrence_waitlist_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrence_waitlist
    ADD CONSTRAINT occurrence_waitlist_pkey PRIMARY KEY (id);


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
-- Name: page_views page_views_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.page_views
    ADD CONSTRAINT page_views_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: payment_settings payment_settings_organizer_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_settings
    ADD CONSTRAINT payment_settings_organizer_id_unique UNIQUE (organizer_id);


--
-- Name: payment_settings payment_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_settings
    ADD CONSTRAINT payment_settings_pkey PRIMARY KEY (id);


--
-- Name: payments payments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_pkey PRIMARY KEY (id);


--
-- Name: payments payments_yoomoney_payment_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_yoomoney_payment_id_unique UNIQUE (yoomoney_payment_id);


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
-- Name: platform_payment_settings platform_payment_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_payment_settings
    ADD CONSTRAINT platform_payment_settings_pkey PRIMARY KEY (id);


--
-- Name: premium_subscriptions premium_subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.premium_subscriptions
    ADD CONSTRAINT premium_subscriptions_pkey PRIMARY KEY (id);


--
-- Name: profile_visits profile_visits_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.profile_visits
    ADD CONSTRAINT profile_visits_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: staff_logs staff_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff_logs
    ADD CONSTRAINT staff_logs_pkey PRIMARY KEY (id);


--
-- Name: subscription_coupon_logs subscription_coupon_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_coupon_logs
    ADD CONSTRAINT subscription_coupon_logs_pkey PRIMARY KEY (id);


--
-- Name: subscription_templates subscription_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_templates
    ADD CONSTRAINT subscription_templates_pkey PRIMARY KEY (id);


--
-- Name: subscription_usages subscription_usages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_usages
    ADD CONSTRAINT subscription_usages_pkey PRIMARY KEY (id);


--
-- Name: subscriptions subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_pkey PRIMARY KEY (id);


--
-- Name: telegram_notify_bindings telegram_notify_bindings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_notify_bindings
    ADD CONSTRAINT telegram_notify_bindings_pkey PRIMARY KEY (id);


--
-- Name: telegram_notify_bindings telegram_notify_bindings_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_notify_bindings
    ADD CONSTRAINT telegram_notify_bindings_token_unique UNIQUE (token);


--
-- Name: event_notification_channels uniq_event_channel_type; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_notification_channels
    ADD CONSTRAINT uniq_event_channel_type UNIQUE (event_id, channel_id, notification_type);


--
-- Name: event_registrations uniq_occ_user; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_registrations
    ADD CONSTRAINT uniq_occ_user UNIQUE (occurrence_id, user_id);


--
-- Name: event_channel_messages uniq_occurrence_channel_type_msg; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_channel_messages
    ADD CONSTRAINT uniq_occurrence_channel_type_msg UNIQUE (occurrence_id, channel_id, notification_type);


--
-- Name: user_notification_channels uniq_user_platform_chat; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_notification_channels
    ADD CONSTRAINT uniq_user_platform_chat UNIQUE (user_id, platform, chat_id);


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
-- Name: user_level_votes user_level_votes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_level_votes
    ADD CONSTRAINT user_level_votes_pkey PRIMARY KEY (id);


--
-- Name: user_level_votes user_level_votes_voter_id_target_id_direction_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_level_votes
    ADD CONSTRAINT user_level_votes_voter_id_target_id_direction_unique UNIQUE (voter_id, target_id, direction);


--
-- Name: user_notification_channels user_notification_channels_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_notification_channels
    ADD CONSTRAINT user_notification_channels_pkey PRIMARY KEY (id);


--
-- Name: user_notifications user_notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_notifications
    ADD CONSTRAINT user_notifications_pkey PRIMARY KEY (id);


--
-- Name: user_play_likes user_play_likes_liker_id_target_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_play_likes
    ADD CONSTRAINT user_play_likes_liker_id_target_id_unique UNIQUE (liker_id, target_id);


--
-- Name: user_play_likes user_play_likes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_play_likes
    ADD CONSTRAINT user_play_likes_pkey PRIMARY KEY (id);


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
-- Name: virtual_wallets virtual_wallets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.virtual_wallets
    ADD CONSTRAINT virtual_wallets_pkey PRIMARY KEY (id);


--
-- Name: virtual_wallets virtual_wallets_user_id_organizer_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.virtual_wallets
    ADD CONSTRAINT virtual_wallets_user_id_organizer_id_unique UNIQUE (user_id, organizer_id);


--
-- Name: virtual_wallets virtual_wallets_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.virtual_wallets
    ADD CONSTRAINT virtual_wallets_user_id_unique UNIQUE (user_id);


--
-- Name: vk_notify_bindings vk_notify_bindings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vk_notify_bindings
    ADD CONSTRAINT vk_notify_bindings_pkey PRIMARY KEY (id);


--
-- Name: vk_notify_bindings vk_notify_bindings_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vk_notify_bindings
    ADD CONSTRAINT vk_notify_bindings_token_unique UNIQUE (token);


--
-- Name: volleyball_schools volleyball_schools_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.volleyball_schools
    ADD CONSTRAINT volleyball_schools_pkey PRIMARY KEY (id);


--
-- Name: volleyball_schools volleyball_schools_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.volleyball_schools
    ADD CONSTRAINT volleyball_schools_slug_unique UNIQUE (slug);


--
-- Name: wallet_transactions wallet_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wallet_transactions
    ADD CONSTRAINT wallet_transactions_pkey PRIMARY KEY (id);


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
-- Name: broadcast_recipients_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX broadcast_recipients_status_index ON public.broadcast_recipients USING btree (status);


--
-- Name: broadcast_recipients_user_notification_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX broadcast_recipients_user_notification_id_index ON public.broadcast_recipients USING btree (user_notification_id);


--
-- Name: broadcasts_created_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX broadcasts_created_by_index ON public.broadcasts USING btree (created_by);


--
-- Name: broadcasts_scheduled_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX broadcasts_scheduled_at_index ON public.broadcasts USING btree (scheduled_at);


--
-- Name: broadcasts_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX broadcasts_status_index ON public.broadcasts USING btree (status);


--
-- Name: channel_bind_requests_platform_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX channel_bind_requests_platform_status_index ON public.channel_bind_requests USING btree (platform, status);


--
-- Name: channel_bind_requests_user_id_platform_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX channel_bind_requests_user_id_platform_index ON public.channel_bind_requests USING btree (user_id, platform);


--
-- Name: cities_country_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cities_country_code_index ON public.cities USING btree (country_code);


--
-- Name: cities_country_code_name_region_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cities_country_code_name_region_index ON public.cities USING btree (country_code, name, region);


--
-- Name: cities_population_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cities_population_index ON public.cities USING btree (population);


--
-- Name: cities_timezone_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cities_timezone_index ON public.cities USING btree (timezone);


--
-- Name: coupon_templates_organizer_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX coupon_templates_organizer_id_is_active_index ON public.coupon_templates USING btree (organizer_id, is_active);


--
-- Name: coupons_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX coupons_code_index ON public.coupons USING btree (code);


--
-- Name: coupons_user_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX coupons_user_id_status_index ON public.coupons USING btree (user_id, status);


--
-- Name: ecm_event_occ_type_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ecm_event_occ_type_idx ON public.event_channel_messages USING btree (event_id, occurrence_id, notification_type);


--
-- Name: er_event_cancelled_at_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX er_event_cancelled_at_idx ON public.event_registrations USING btree (event_id, cancelled_at);


--
-- Name: event_game_settings_gender_policy_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_game_settings_gender_policy_index ON public.event_game_settings USING btree (gender_policy);


--
-- Name: event_game_settings_subtype_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_game_settings_subtype_index ON public.event_game_settings USING btree (subtype);


--
-- Name: event_notification_channels_event_id_notification_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_notification_channels_event_id_notification_type_index ON public.event_notification_channels USING btree (event_id, notification_type);


--
-- Name: event_occurrences_event_id_starts_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_occurrences_event_id_starts_at_index ON public.event_occurrences USING btree (event_id, starts_at);


--
-- Name: event_occurrences_event_uniq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX event_occurrences_event_uniq ON public.event_occurrences USING btree (event_id, uniq_key);


--
-- Name: event_occurrences_location_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_occurrences_location_id_index ON public.event_occurrences USING btree (location_id);


--
-- Name: event_occurrences_starts_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_occurrences_starts_at_index ON public.event_occurrences USING btree (starts_at);


--
-- Name: event_registration_group_invites_event_id_group_key_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_registration_group_invites_event_id_group_key_index ON public.event_registration_group_invites USING btree (event_id, group_key);


--
-- Name: event_registration_group_invites_to_user_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_registration_group_invites_to_user_id_status_index ON public.event_registration_group_invites USING btree (to_user_id, status);


--
-- Name: event_registrations_cancelled_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_registrations_cancelled_at_index ON public.event_registrations USING btree (cancelled_at);


--
-- Name: event_registrations_event_id_group_key_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_registrations_event_id_group_key_index ON public.event_registrations USING btree (event_id, group_key);


--
-- Name: event_registrations_event_id_position_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_registrations_event_id_position_index ON public.event_registrations USING btree (event_id, "position");


--
-- Name: event_registrations_is_cancelled_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_registrations_is_cancelled_index ON public.event_registrations USING btree (is_cancelled);


--
-- Name: event_registrations_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_registrations_status_index ON public.event_registrations USING btree (status);


--
-- Name: event_team_applications_event_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_team_applications_event_id_status_index ON public.event_team_applications USING btree (event_id, status);


--
-- Name: event_team_applications_reviewed_by_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_team_applications_reviewed_by_user_id_index ON public.event_team_applications USING btree (reviewed_by_user_id);


--
-- Name: event_team_applications_submitted_by_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_team_applications_submitted_by_user_id_index ON public.event_team_applications USING btree (submitted_by_user_id);


--
-- Name: event_team_invites_event_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_team_invites_event_id_status_index ON public.event_team_invites USING btree (event_id, status);


--
-- Name: event_team_invites_event_team_id_invited_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_team_invites_event_team_id_invited_user_id_index ON public.event_team_invites USING btree (event_team_id, invited_user_id);


--
-- Name: event_team_member_audits_event_team_id_action_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_team_member_audits_event_team_id_action_index ON public.event_team_member_audits USING btree (event_team_id, action);


--
-- Name: event_team_member_audits_performed_by_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_team_member_audits_performed_by_user_id_index ON public.event_team_member_audits USING btree (performed_by_user_id);


--
-- Name: event_team_member_audits_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_team_member_audits_user_id_index ON public.event_team_member_audits USING btree (user_id);


--
-- Name: event_team_members_event_team_id_confirmation_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_team_members_event_team_id_confirmation_status_index ON public.event_team_members USING btree (event_team_id, confirmation_status);


--
-- Name: event_team_members_event_team_id_position_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_team_members_event_team_id_position_code_index ON public.event_team_members USING btree (event_team_id, position_code);


--
-- Name: event_team_members_event_team_id_role_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_team_members_event_team_id_role_code_index ON public.event_team_members USING btree (event_team_id, role_code);


--
-- Name: event_team_members_event_team_id_team_role_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_team_members_event_team_id_team_role_index ON public.event_team_members USING btree (event_team_id, team_role);


--
-- Name: event_team_members_invited_by_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_team_members_invited_by_user_id_index ON public.event_team_members USING btree (invited_by_user_id);


--
-- Name: event_team_members_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_team_members_user_id_index ON public.event_team_members USING btree (user_id);


--
-- Name: event_teams_captain_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_teams_captain_user_id_index ON public.event_teams USING btree (captain_user_id);


--
-- Name: event_teams_event_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_teams_event_id_status_index ON public.event_teams USING btree (event_id, status);


--
-- Name: event_teams_event_id_team_kind_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_teams_event_id_team_kind_index ON public.event_teams USING btree (event_id, team_kind);


--
-- Name: event_teams_is_complete_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_teams_is_complete_index ON public.event_teams USING btree (is_complete);


--
-- Name: event_teams_occurrence_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_teams_occurrence_id_status_index ON public.event_teams USING btree (occurrence_id, status);


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
-- Name: event_tournament_settings_registration_mode_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_tournament_settings_registration_mode_index ON public.event_tournament_settings USING btree (registration_mode);


--
-- Name: event_tournament_settings_seeding_mode_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_tournament_settings_seeding_mode_index ON public.event_tournament_settings USING btree (seeding_mode);


--
-- Name: event_trainers_event_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_trainers_event_idx ON public.event_trainers USING btree (event_id);


--
-- Name: event_trainers_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX event_trainers_unique ON public.event_trainers USING btree (event_id, user_id);


--
-- Name: event_trainers_user_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX event_trainers_user_idx ON public.event_trainers USING btree (user_id);


--
-- Name: events_cancel_self_until_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_cancel_self_until_index ON public.events USING btree (cancel_self_until);


--
-- Name: events_is_paid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_is_paid_index ON public.events USING btree (is_paid);


--
-- Name: events_location_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_location_id_index ON public.events USING btree (location_id);


--
-- Name: events_organizer_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_organizer_id_index ON public.events USING btree (organizer_id);


--
-- Name: events_registration_ends_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_registration_ends_at_index ON public.events USING btree (registration_ends_at);


--
-- Name: events_registration_starts_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_registration_starts_at_index ON public.events USING btree (registration_starts_at);


--
-- Name: events_starts_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_starts_at_index ON public.events USING btree (starts_at);


--
-- Name: events_visibility_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_visibility_index ON public.events USING btree (visibility);


--
-- Name: friendships_friend_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX friendships_friend_id_index ON public.friendships USING btree (friend_id);


--
-- Name: idx_event_role_slots_available; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_event_role_slots_available ON public.event_role_slots USING btree (event_id) WHERE (taken_slots < max_slots);


--
-- Name: idx_event_role_slots_event; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_event_role_slots_event ON public.event_role_slots USING btree (event_id);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: locations_city_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX locations_city_id_index ON public.locations USING btree (city_id);


--
-- Name: locations_organizer_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX locations_organizer_id_index ON public.locations USING btree (organizer_id);


--
-- Name: max_bindings_user_id_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX max_bindings_user_id_expires_at_index ON public.max_bindings USING btree (user_id, expires_at);


--
-- Name: media_model_type_model_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX media_model_type_model_id_index ON public.media USING btree (model_type, model_id);


--
-- Name: media_order_column_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX media_order_column_index ON public.media USING btree (order_column);


--
-- Name: notification_deliveries_channel_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_deliveries_channel_index ON public.notification_deliveries USING btree (channel);


--
-- Name: notification_deliveries_event_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_deliveries_event_id_index ON public.notification_deliveries USING btree (event_id);


--
-- Name: notification_deliveries_occurrence_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_deliveries_occurrence_id_index ON public.notification_deliveries USING btree (occurrence_id);


--
-- Name: notification_deliveries_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_deliveries_status_index ON public.notification_deliveries USING btree (status);


--
-- Name: notification_deliveries_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_deliveries_type_index ON public.notification_deliveries USING btree (type);


--
-- Name: notification_deliveries_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_deliveries_user_id_index ON public.notification_deliveries USING btree (user_id);


--
-- Name: notification_templates_code_channel_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_templates_code_channel_index ON public.notification_templates USING btree (code, channel);


--
-- Name: notification_templates_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_templates_is_active_index ON public.notification_templates USING btree (is_active);


--
-- Name: occurrence_waitlist_occurrence_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX occurrence_waitlist_occurrence_id_created_at_index ON public.occurrence_waitlist USING btree (occurrence_id, created_at);


--
-- Name: organizer_requests_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX organizer_requests_status_index ON public.organizer_requests USING btree (status);


--
-- Name: organizer_requests_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX organizer_requests_user_id_index ON public.organizer_requests USING btree (user_id);


--
-- Name: page_views_entity_type_entity_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX page_views_entity_type_entity_id_created_at_index ON public.page_views USING btree (entity_type, entity_id, created_at);


--
-- Name: page_views_entity_type_entity_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX page_views_entity_type_entity_id_index ON public.page_views USING btree (entity_type, entity_id);


--
-- Name: page_views_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX page_views_user_id_index ON public.page_views USING btree (user_id);


--
-- Name: payments_status_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payments_status_expires_at_index ON public.payments USING btree (status, expires_at);


--
-- Name: payments_user_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payments_user_id_status_index ON public.payments USING btree (user_id, status);


--
-- Name: personal_access_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: premium_subscriptions_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX premium_subscriptions_expires_at_index ON public.premium_subscriptions USING btree (expires_at);


--
-- Name: premium_subscriptions_user_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX premium_subscriptions_user_id_status_index ON public.premium_subscriptions USING btree (user_id, status);


--
-- Name: profile_visits_profile_user_id_visited_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX profile_visits_profile_user_id_visited_at_index ON public.profile_visits USING btree (profile_user_id, visited_at);


--
-- Name: profile_visits_visitor_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX profile_visits_visitor_user_id_index ON public.profile_visits USING btree (visitor_user_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: staff_logs_organizer_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX staff_logs_organizer_id_created_at_index ON public.staff_logs USING btree (organizer_id, created_at);


--
-- Name: staff_logs_staff_user_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX staff_logs_staff_user_id_created_at_index ON public.staff_logs USING btree (staff_user_id, created_at);


--
-- Name: subscription_coupon_logs_entity_type_entity_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscription_coupon_logs_entity_type_entity_id_index ON public.subscription_coupon_logs USING btree (entity_type, entity_id);


--
-- Name: subscription_coupon_logs_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscription_coupon_logs_user_id_index ON public.subscription_coupon_logs USING btree (user_id);


--
-- Name: subscription_templates_organizer_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscription_templates_organizer_id_is_active_index ON public.subscription_templates USING btree (organizer_id, is_active);


--
-- Name: subscription_usages_occurrence_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscription_usages_occurrence_id_index ON public.subscription_usages USING btree (occurrence_id);


--
-- Name: subscription_usages_subscription_id_action_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscription_usages_subscription_id_action_index ON public.subscription_usages USING btree (subscription_id, action);


--
-- Name: subscriptions_organizer_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscriptions_organizer_id_status_index ON public.subscriptions USING btree (organizer_id, status);


--
-- Name: subscriptions_user_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscriptions_user_id_status_index ON public.subscriptions USING btree (user_id, status);


--
-- Name: telegram_notify_bindings_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telegram_notify_bindings_user_id_index ON public.telegram_notify_bindings USING btree (user_id);


--
-- Name: user_notification_channels_user_id_platform_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_notification_channels_user_id_platform_index ON public.user_notification_channels USING btree (user_id, platform);


--
-- Name: user_notifications_user_id_read_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_notifications_user_id_read_at_index ON public.user_notifications USING btree (user_id, read_at);


--
-- Name: user_notifications_user_id_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_notifications_user_id_type_index ON public.user_notifications USING btree (user_id, type);


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
-- Name: users_fullname_trgm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_fullname_trgm_idx ON public.users USING gin (lower((((first_name)::text || ' '::text) || (last_name)::text)) public.gin_trgm_ops);


--
-- Name: users_gender_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_gender_index ON public.users USING btree (gender);


--
-- Name: users_height_cm_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_height_cm_index ON public.users USING btree (height_cm);


--
-- Name: users_is_bot_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_is_bot_index ON public.users USING btree (is_bot);


--
-- Name: users_max_chat_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_max_chat_id_index ON public.users USING btree (max_chat_id);


--
-- Name: users_role_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_role_index ON public.users USING btree (role);


--
-- Name: users_search_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_search_idx ON public.users USING btree (last_name, first_name);


--
-- Name: users_telegram_notify_chat_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_telegram_notify_chat_id_index ON public.users USING btree (telegram_notify_chat_id);


--
-- Name: users_vk_notify_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_vk_notify_user_id_index ON public.users USING btree (vk_notify_user_id);


--
-- Name: vk_notify_bindings_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vk_notify_bindings_user_id_index ON public.vk_notify_bindings USING btree (user_id);


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
-- Name: broadcast_recipients broadcast_recipients_broadcast_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.broadcast_recipients
    ADD CONSTRAINT broadcast_recipients_broadcast_id_foreign FOREIGN KEY (broadcast_id) REFERENCES public.broadcasts(id) ON DELETE CASCADE;


--
-- Name: broadcast_recipients broadcast_recipients_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.broadcast_recipients
    ADD CONSTRAINT broadcast_recipients_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: broadcasts broadcasts_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.broadcasts
    ADD CONSTRAINT broadcasts_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: channel_bind_requests channel_bind_requests_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.channel_bind_requests
    ADD CONSTRAINT channel_bind_requests_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: coupon_templates coupon_templates_organizer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.coupon_templates
    ADD CONSTRAINT coupon_templates_organizer_id_foreign FOREIGN KEY (organizer_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: coupons coupons_organizer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.coupons
    ADD CONSTRAINT coupons_organizer_id_foreign FOREIGN KEY (organizer_id) REFERENCES public.users(id);


--
-- Name: coupons coupons_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.coupons
    ADD CONSTRAINT coupons_template_id_foreign FOREIGN KEY (template_id) REFERENCES public.coupon_templates(id);


--
-- Name: coupons coupons_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.coupons
    ADD CONSTRAINT coupons_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: event_channel_messages event_channel_messages_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_channel_messages
    ADD CONSTRAINT event_channel_messages_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.user_notification_channels(id) ON DELETE CASCADE;


--
-- Name: event_channel_messages event_channel_messages_event_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_channel_messages
    ADD CONSTRAINT event_channel_messages_event_id_foreign FOREIGN KEY (event_id) REFERENCES public.events(id) ON DELETE CASCADE;


--
-- Name: event_channel_messages event_channel_messages_occurrence_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_channel_messages
    ADD CONSTRAINT event_channel_messages_occurrence_id_foreign FOREIGN KEY (occurrence_id) REFERENCES public.event_occurrences(id) ON DELETE CASCADE;


--
-- Name: event_game_settings event_game_settings_event_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_game_settings
    ADD CONSTRAINT event_game_settings_event_id_foreign FOREIGN KEY (event_id) REFERENCES public.events(id) ON DELETE CASCADE;


--
-- Name: event_notification_channels event_notification_channels_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_notification_channels
    ADD CONSTRAINT event_notification_channels_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.user_notification_channels(id) ON DELETE CASCADE;


--
-- Name: event_notification_channels event_notification_channels_event_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_notification_channels
    ADD CONSTRAINT event_notification_channels_event_id_foreign FOREIGN KEY (event_id) REFERENCES public.events(id) ON DELETE CASCADE;


--
-- Name: event_occurrence_stats event_occurrence_stats_occurrence_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_occurrence_stats
    ADD CONSTRAINT event_occurrence_stats_occurrence_id_foreign FOREIGN KEY (occurrence_id) REFERENCES public.event_occurrences(id) ON DELETE CASCADE;


--
-- Name: event_occurrences event_occurrences_event_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_occurrences
    ADD CONSTRAINT event_occurrences_event_id_foreign FOREIGN KEY (event_id) REFERENCES public.events(id) ON DELETE CASCADE;


--
-- Name: event_registration_group_invites event_registration_group_invites_event_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_registration_group_invites
    ADD CONSTRAINT event_registration_group_invites_event_id_foreign FOREIGN KEY (event_id) REFERENCES public.events(id) ON DELETE CASCADE;


--
-- Name: event_registration_group_invites event_registration_group_invites_from_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_registration_group_invites
    ADD CONSTRAINT event_registration_group_invites_from_user_id_foreign FOREIGN KEY (from_user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: event_registration_group_invites event_registration_group_invites_to_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_registration_group_invites
    ADD CONSTRAINT event_registration_group_invites_to_user_id_foreign FOREIGN KEY (to_user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: event_registrations event_registrations_event_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_registrations
    ADD CONSTRAINT event_registrations_event_id_foreign FOREIGN KEY (event_id) REFERENCES public.events(id) ON DELETE CASCADE;


--
-- Name: event_registrations event_registrations_occurrence_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_registrations
    ADD CONSTRAINT event_registrations_occurrence_id_foreign FOREIGN KEY (occurrence_id) REFERENCES public.event_occurrences(id) ON DELETE CASCADE;


--
-- Name: event_registrations event_registrations_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_registrations
    ADD CONSTRAINT event_registrations_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: event_role_slots event_role_slots_event_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_role_slots
    ADD CONSTRAINT event_role_slots_event_id_foreign FOREIGN KEY (event_id) REFERENCES public.events(id) ON DELETE CASCADE;


--
-- Name: event_team_applications event_team_applications_event_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_applications
    ADD CONSTRAINT event_team_applications_event_id_foreign FOREIGN KEY (event_id) REFERENCES public.events(id) ON DELETE CASCADE;


--
-- Name: event_team_applications event_team_applications_event_team_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_applications
    ADD CONSTRAINT event_team_applications_event_team_id_foreign FOREIGN KEY (event_team_id) REFERENCES public.event_teams(id) ON DELETE CASCADE;


--
-- Name: event_team_applications event_team_applications_reviewed_by_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_applications
    ADD CONSTRAINT event_team_applications_reviewed_by_user_id_foreign FOREIGN KEY (reviewed_by_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: event_team_applications event_team_applications_submitted_by_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_applications
    ADD CONSTRAINT event_team_applications_submitted_by_user_id_foreign FOREIGN KEY (submitted_by_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: event_team_invites event_team_invites_event_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_invites
    ADD CONSTRAINT event_team_invites_event_id_foreign FOREIGN KEY (event_id) REFERENCES public.events(id) ON DELETE CASCADE;


--
-- Name: event_team_invites event_team_invites_event_team_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_invites
    ADD CONSTRAINT event_team_invites_event_team_id_foreign FOREIGN KEY (event_team_id) REFERENCES public.event_teams(id) ON DELETE CASCADE;


--
-- Name: event_team_invites event_team_invites_invited_by_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_invites
    ADD CONSTRAINT event_team_invites_invited_by_user_id_foreign FOREIGN KEY (invited_by_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: event_team_invites event_team_invites_invited_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_invites
    ADD CONSTRAINT event_team_invites_invited_user_id_foreign FOREIGN KEY (invited_user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: event_team_member_audits event_team_member_audits_event_team_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_member_audits
    ADD CONSTRAINT event_team_member_audits_event_team_id_foreign FOREIGN KEY (event_team_id) REFERENCES public.event_teams(id) ON DELETE CASCADE;


--
-- Name: event_team_member_audits event_team_member_audits_performed_by_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_member_audits
    ADD CONSTRAINT event_team_member_audits_performed_by_user_id_foreign FOREIGN KEY (performed_by_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: event_team_member_audits event_team_member_audits_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_member_audits
    ADD CONSTRAINT event_team_member_audits_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: event_team_members event_team_members_event_team_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_members
    ADD CONSTRAINT event_team_members_event_team_id_foreign FOREIGN KEY (event_team_id) REFERENCES public.event_teams(id) ON DELETE CASCADE;


--
-- Name: event_team_members event_team_members_invited_by_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_members
    ADD CONSTRAINT event_team_members_invited_by_user_id_foreign FOREIGN KEY (invited_by_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: event_team_members event_team_members_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_team_members
    ADD CONSTRAINT event_team_members_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: event_teams event_teams_captain_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_teams
    ADD CONSTRAINT event_teams_captain_user_id_foreign FOREIGN KEY (captain_user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: event_teams event_teams_event_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_teams
    ADD CONSTRAINT event_teams_event_id_foreign FOREIGN KEY (event_id) REFERENCES public.events(id) ON DELETE CASCADE;


--
-- Name: event_teams event_teams_occurrence_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_teams
    ADD CONSTRAINT event_teams_occurrence_id_foreign FOREIGN KEY (occurrence_id) REFERENCES public.event_occurrences(id) ON DELETE SET NULL;


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
-- Name: event_tournament_settings event_tournament_settings_event_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_tournament_settings
    ADD CONSTRAINT event_tournament_settings_event_id_foreign FOREIGN KEY (event_id) REFERENCES public.events(id) ON DELETE CASCADE;


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
-- Name: events events_trainer_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_trainer_user_id_foreign FOREIGN KEY (trainer_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: friendships friendships_friend_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.friendships
    ADD CONSTRAINT friendships_friend_id_foreign FOREIGN KEY (friend_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: friendships friendships_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.friendships
    ADD CONSTRAINT friendships_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: locations locations_city_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locations
    ADD CONSTRAINT locations_city_id_foreign FOREIGN KEY (city_id) REFERENCES public.cities(id) ON DELETE SET NULL;


--
-- Name: locations locations_organizer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locations
    ADD CONSTRAINT locations_organizer_id_foreign FOREIGN KEY (organizer_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: max_bindings max_bindings_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.max_bindings
    ADD CONSTRAINT max_bindings_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: occurrence_waitlist occurrence_waitlist_occurrence_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrence_waitlist
    ADD CONSTRAINT occurrence_waitlist_occurrence_id_foreign FOREIGN KEY (occurrence_id) REFERENCES public.event_occurrences(id) ON DELETE CASCADE;


--
-- Name: occurrence_waitlist occurrence_waitlist_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrence_waitlist
    ADD CONSTRAINT occurrence_waitlist_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


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
-- Name: payment_settings payment_settings_organizer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_settings
    ADD CONSTRAINT payment_settings_organizer_id_foreign FOREIGN KEY (organizer_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: payments payments_organizer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_organizer_id_foreign FOREIGN KEY (organizer_id) REFERENCES public.users(id);


--
-- Name: payments payments_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: premium_subscriptions premium_subscriptions_referred_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.premium_subscriptions
    ADD CONSTRAINT premium_subscriptions_referred_by_foreign FOREIGN KEY (referred_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: premium_subscriptions premium_subscriptions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.premium_subscriptions
    ADD CONSTRAINT premium_subscriptions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: profile_visits profile_visits_profile_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.profile_visits
    ADD CONSTRAINT profile_visits_profile_user_id_foreign FOREIGN KEY (profile_user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: profile_visits profile_visits_visitor_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.profile_visits
    ADD CONSTRAINT profile_visits_visitor_user_id_foreign FOREIGN KEY (visitor_user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: staff_logs staff_logs_organizer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff_logs
    ADD CONSTRAINT staff_logs_organizer_id_foreign FOREIGN KEY (organizer_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: staff_logs staff_logs_staff_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff_logs
    ADD CONSTRAINT staff_logs_staff_user_id_foreign FOREIGN KEY (staff_user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: subscription_templates subscription_templates_organizer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_templates
    ADD CONSTRAINT subscription_templates_organizer_id_foreign FOREIGN KEY (organizer_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: subscription_usages subscription_usages_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_usages
    ADD CONSTRAINT subscription_usages_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id) ON DELETE CASCADE;


--
-- Name: subscriptions subscriptions_organizer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_organizer_id_foreign FOREIGN KEY (organizer_id) REFERENCES public.users(id);


--
-- Name: subscriptions subscriptions_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_template_id_foreign FOREIGN KEY (template_id) REFERENCES public.subscription_templates(id);


--
-- Name: subscriptions subscriptions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


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
-- Name: user_level_votes user_level_votes_target_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_level_votes
    ADD CONSTRAINT user_level_votes_target_id_foreign FOREIGN KEY (target_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_level_votes user_level_votes_voter_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_level_votes
    ADD CONSTRAINT user_level_votes_voter_id_foreign FOREIGN KEY (voter_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_notification_channels user_notification_channels_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_notification_channels
    ADD CONSTRAINT user_notification_channels_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_notifications user_notifications_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_notifications
    ADD CONSTRAINT user_notifications_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_play_likes user_play_likes_liker_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_play_likes
    ADD CONSTRAINT user_play_likes_liker_id_foreign FOREIGN KEY (liker_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_play_likes user_play_likes_target_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_play_likes
    ADD CONSTRAINT user_play_likes_target_id_foreign FOREIGN KEY (target_id) REFERENCES public.users(id) ON DELETE CASCADE;


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
-- Name: users users_avatar_media_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_avatar_media_id_foreign FOREIGN KEY (avatar_media_id) REFERENCES public.media(id) ON DELETE SET NULL;


--
-- Name: users users_city_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_city_id_foreign FOREIGN KEY (city_id) REFERENCES public.cities(id);


--
-- Name: virtual_wallets virtual_wallets_organizer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.virtual_wallets
    ADD CONSTRAINT virtual_wallets_organizer_id_foreign FOREIGN KEY (organizer_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: virtual_wallets virtual_wallets_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.virtual_wallets
    ADD CONSTRAINT virtual_wallets_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: volleyball_schools volleyball_schools_organizer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.volleyball_schools
    ADD CONSTRAINT volleyball_schools_organizer_id_foreign FOREIGN KEY (organizer_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: wallet_transactions wallet_transactions_wallet_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wallet_transactions
    ADD CONSTRAINT wallet_transactions_wallet_id_foreign FOREIGN KEY (wallet_id) REFERENCES public.virtual_wallets(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict LoKldAp9BBD6zZbRtwTKj0GvUCWSc6cRQY2JNMk8YfCMql8tkgXSipFKNVGXJxJ

--
-- PostgreSQL database dump
--

\restrict pWeknHdqSbdiAYvy29vfNX02FYrrBqLjpWJTMST4Ng6qHpexdAmdn8qqO1bVIVW

-- Dumped from database version 16.13 (Ubuntu 16.13-0ubuntu0.24.04.1)
-- Dumped by pg_dump version 16.13 (Ubuntu 16.13-0ubuntu0.24.04.1)

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
61	2026_01_29_101130_create_event_game_settings_table	41
62	2026_01_29_105322_add_level_max_columns_to_events_table	42
63	2026_01_29_150000_drop_legacy_columns_from_events_table	43
64	2026_01_29_160000_add_position_to_event_registrations_table	44
65	2026_01_29_194620_add_gender_policy_to_event_game_settings_table	45
66	2026_01_29_210000_add_gender_policy_to_event_game_settings_table	46
67	2026_01_30_050609_backfill_gender_policy_from_legacy_in_event_game_settings	47
68	2026_02_01_000001_add_cancel_fields_to_event_registrations_table	48
69	2026_02_01_876345_add_cancelled_at_to_event_registrations	49
70	2026_02_01_114038_add_event_cancelled_at_index_to_event_registrations_table	50
71	2026_02_01_000001_create_event_occurrences_table	51
72	2026_02_01_000002_add_occurrence_id_to_event_registrations	51
74	2026_02_02_000003_drop_old_unique_event_user_on_event_registrations	52
75	2026_02_02_142424_add_timezone_to_users_table	53
76	2026_02_02_161711_add_timezone_to_users_table	54
77	2026_02_03_062918_alter_cities_add_geo_fields	55
78	2026_02_08_080400_add_private_fields_to_events_table	56
79	2026_02_09_061708_add_trainer_user_id_to_events_table	57
80	2026_02_15_095057_add_registration_fields_to_events_table	58
81	2026_02_15_000001_add_snapshots_to_event_occurrences	59
82	2026_02_15_000002_backfill_occurrence_snapshots	60
83	2026_02_16_073238_patch_event_occurrences_snapshot_timestamptz	61
84	2026_02_16_073338_backfill_event_occurrences_snapshot_nullable_fields	61
85	2026_02_17_085440_add_age_policy_and_is_snow_to_events_table	62
86	2026_02_17_085515_add_age_policy_and_is_snow_to_event_occurrences_table	62
88	2026_02_18_152017_add_reminder_and_visibility_flags_to_events_table	63
89	2026_02_18_152053_add_reminder_and_visibility_flags_to_event_occurrences_table	63
90	2026_02_18_153536_create_notification_deliveries_table	64
91	2026_02_20_263127_update_locations_for_cities_and_texts	65
92	2026_02_20_125303_backfill_locations_city_id_from_city	66
93	2026_02_20_130309_normalize_locations_city_id_to_best_city	67
94	2026_02_20_131117_drop_city_string_from_locations	68
95	2026_02_23_094153_add_description_html_to_events_table	69
96	2026_02_27_051538_add_duration_sec_to_events_table	70
97	2026_02_27_051613_add_duration_sec_to_event_occurrences_table	70
98	2026_03_08_032137_create_event_occurrence_stats	71
99	2026_03_09_180430_add_teams_count_to_event_game_settings_table	72
100	2026_03_09_184141_create_event_role_slots_table	73
101	2026_03_18_185710_add_registration_mode_to_events_table	74
102	2026_03_18_185721_add_group_key_to_event_registrations_table	74
103	2026_03_18_195603_create_event_registration_group_invites_table	75
104	2026_03_18_200720_add_auto_join_after_registration_to_event_registration_group_invites_table	76
105	2026_03_19_044603_create_user_notifications_table	77
106	2026_03_20_164008_add_max_fields_to_users_table	78
107	2026_03_20_164035_create_max_bindings_table	78
108	2026_03_21_064914_create_notification_templates_table	79
109	2026_03_21_064946_create_broadcasts_table	79
110	2026_03_21_065021_create_broadcast_recipients_table	79
111	2026_03_21_155525_create_event_tournament_settings_table	80
112	2026_03_21_155555_create_event_teams_table	80
113	2026_03_21_155625_create_event_team_members_table	80
114	2026_03_21_155753_create_event_team_member_audits_table	80
115	2026_03_21_155829_create_event_team_applications_table	80
116	2026_03_22_075422_alter_event_team_members_add_team_role_and_position_code	81
117	2026_03_22_075458_backfill_event_team_members_team_role_and_position_code	81
118	2026_03_22_190400_alter_event_tournament_settings_add_scheme_and_team_limits	82
119	2026_03_23_035814_add_avatar_fields_to_users_table	83
120	2026_03_23_042618_drop_avatar_provider_url_from_users_table	84
123	2026_03_23_190504_migration_event_team_invites_00555_23032026	85
124	2026_03_23_202330_create_event_team_invites_table_v2	85
125	2026_03_24_141218_add_teams_count_to_events_table	86
126	2026_03_24_145623_add_teams_count_to_event_tournament_settings_table	87
127	2026_03_29_053200_create_user_notification_channels_table	88
128	2026_03_29_053349_create_channel_bind_requests_table	88
129	2026_03_29_053609_create_event_notification_channels_table	88
130	2026_03_29_053658_create_event_channel_messages_table	88
131	2026_03_29_090552_alter_notification_channel_tables_add_verified_at_and_cleanup_bind_requests	89
132	2026_03_29_093043_patch_notification_channel_tables	90
133	2026_03_30_191500_add_notification_targets_to_users_table	91
134	2026_03_30_192701_create_telegram_notify_bindings_table	92
135	2026_03_30_192702_create_vk_notify_bindings_table	92
136	2026_03_30_195648_alter_notify_bindings_add_state_columns	93
137	2026_04_02_161933_add_price_minor_and_currency_to_events_table	94
138	2026_04_03_101055_drop_timezone_from_users_table	95
139	2026_04_03_162858_add_child_age_range_to_events_table	96
140	2026_04_05_050903_2026_04_05_000001_add_is_bot_to_users	97
141	2026_04_05_050924_2026_04_05_000002_add_bot_assistant_to_events	97
142	2026_04_05_084102_add_event_photos_field_to_events_table	98
143	2026_04_07_071617_create_occurrence_waitlist_table	99
144	2026_04_08_091815_add_hide_age_to_users_table	100
145	2026_04_08_100544_add_missing_notification_templates	101
146	2026_04_08_111731_create_user_level_votes_table	102
147	2026_04_08_111759_create_user_play_likes_table	102
148	2026_04_08_154634_create_volleyball_schools_table	103
149	2026_04_08_202849_create_payment_settings_table	104
150	2026_04_08_202850_create_payments_table	104
151	2026_04_08_202850_create_virtual_wallets_table	104
152	2026_04_08_202850_create_wallet_transactions_table	104
153	2026_04_08_202851_add_payment_fields_to_event_registrations_table	104
154	2026_04_08_202851_add_payment_fields_to_events_table	104
155	2026_04_09_073332_create_page_views_table	105
156	2026_04_10_092306_create_subscription_templates_table	106
157	2026_04_10_092307_create_coupon_templates_table	106
158	2026_04_10_092307_create_coupons_table	106
159	2026_04_10_092307_create_subscription_coupon_logs_table	106
160	2026_04_10_092307_create_subscription_usages_table	107
161	2026_04_10_092307_create_subscriptions_table	107
162	2026_04_10_093201_add_foreign_keys_to_subscription_usages_table	108
163	2026_04_10_100126_add_subscription_coupon_to_event_registrations	109
164	2026_04_10_100948_add_confirmed_at_to_event_registrations	110
165	2026_04_11_025507_add_auto_booked_to_event_registrations	111
166	2026_04_11_172025_create_premium_subscriptions_table	112
167	2026_04_11_182714_create_friendships_table	113
168	2026_04_11_182714_create_profile_visits_table	113
169	2026_04_11_191820_add_notification_settings_to_premium_subscriptions	114
170	2026_04_12_035508_create_platform_payment_settings_table	115
171	2026_04_12_043526_add_pending_status_to_premium_subscriptions_status	116
172	2026_04_12_043743_make_organizer_id_nullable_in_payments	117
173	2026_04_12_074247_add_social_links_to_volleyball_schools	118
174	2026_04_12_131844_create_staff_assignments_table	119
175	2026_04_12_131844_create_staff_logs_table	119
176	2026_04_12_154502_add_duration_to_subscription_templates	120
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 176, true);


--
-- PostgreSQL database dump complete
--

\unrestrict pWeknHdqSbdiAYvy29vfNX02FYrrBqLjpWJTMST4Ng6qHpexdAmdn8qqO1bVIVW

