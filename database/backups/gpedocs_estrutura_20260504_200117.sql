--
-- PostgreSQL database dump
--

\restrict ZiChe2SLHjo2KT6h0xbcIUPSZBy4GSSXWtEQjjNOn3VcwZnPU4sHE3ExROjIybc

-- Dumped from database version 18.3
-- Dumped by pg_dump version 18.3

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
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
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


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
-- Name: ged_assinaturas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_assinaturas (
    id bigint NOT NULL,
    solicitacao_id bigint NOT NULL,
    documento_id bigint NOT NULL,
    signatario_id bigint NOT NULL,
    ordem integer DEFAULT 0 NOT NULL,
    status character varying(20) DEFAULT 'pendente'::character varying NOT NULL,
    email_signatario character varying(255) NOT NULL,
    cpf_signatario character varying(14),
    ip character varying(45),
    geolocalizacao character varying(255),
    user_agent text,
    hash_documento character varying(64),
    versao_id bigint,
    motivo_recusa text,
    assinado_em timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tipo_assinatura character varying(20) DEFAULT 'simples'::character varying NOT NULL,
    certificado_id bigint,
    assinatura_pkcs7 bytea,
    cadeia_certificados json,
    politica_assinatura character varying(120),
    algoritmo_hash character varying(20),
    arquivo_assinado_path character varying(500),
    hash_assinatura_sha256 character varying(64),
    timestamp_assinatura timestamp(0) without time zone
);


--
-- Name: ged_assinaturas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ged_assinaturas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ged_assinaturas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ged_assinaturas_id_seq OWNED BY public.ged_assinaturas.id;


--
-- Name: ged_audit_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_audit_logs (
    id bigint NOT NULL,
    documento_id bigint,
    usuario_id bigint,
    acao character varying(50) NOT NULL,
    detalhes jsonb,
    ip character varying(45),
    user_agent text,
    created_at timestamp(0) without time zone
);


--
-- Name: ged_audit_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ged_audit_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ged_audit_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ged_audit_logs_id_seq OWNED BY public.ged_audit_logs.id;


--
-- Name: ged_buscas_salvas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_buscas_salvas (
    id bigint NOT NULL,
    usuario_id bigint NOT NULL,
    nome character varying(100) NOT NULL,
    filtros jsonb NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ged_buscas_salvas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ged_buscas_salvas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ged_buscas_salvas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ged_buscas_salvas_id_seq OWNED BY public.ged_buscas_salvas.id;


--
-- Name: ged_certificados; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_certificados (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    tipo character varying(5) NOT NULL,
    subject_cn character varying(255) NOT NULL,
    subject_cpf character varying(14),
    subject_dn character varying(1000) NOT NULL,
    issuer_cn character varying(255) NOT NULL,
    issuer_dn character varying(1000) NOT NULL,
    serial_number character varying(80) NOT NULL,
    thumbprint_sha1 character varying(40) NOT NULL,
    thumbprint_sha256 character varying(64) NOT NULL,
    valido_de timestamp(0) without time zone NOT NULL,
    valido_ate timestamp(0) without time zone NOT NULL,
    certificado_pem text NOT NULL,
    cadeia_pem json,
    politica_oid character varying(80),
    icp_brasil boolean DEFAULT true NOT NULL,
    revogado boolean DEFAULT false NOT NULL,
    verificado_em timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ged_certificados_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ged_certificados_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ged_certificados_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ged_certificados_id_seq OWNED BY public.ged_certificados.id;


--
-- Name: ged_compartilhamentos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_compartilhamentos (
    id bigint NOT NULL,
    documento_id bigint NOT NULL,
    usuario_id bigint NOT NULL,
    permissao character varying(20) DEFAULT 'visualizar'::character varying NOT NULL,
    criado_por bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ged_compartilhamentos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ged_compartilhamentos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ged_compartilhamentos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ged_compartilhamentos_id_seq OWNED BY public.ged_compartilhamentos.id;


--
-- Name: ged_documento_tags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_documento_tags (
    documento_id bigint NOT NULL,
    tag_id bigint NOT NULL
);


--
-- Name: ged_documentos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_documentos (
    id bigint NOT NULL,
    nome character varying(255) NOT NULL,
    descricao text,
    tipo_documental_id bigint,
    pasta_id bigint,
    versao_atual integer DEFAULT 1 NOT NULL,
    tamanho bigint NOT NULL,
    mime_type character varying(100) NOT NULL,
    autor_id bigint NOT NULL,
    status character varying(20) DEFAULT 'rascunho'::character varying NOT NULL,
    classificacao character varying(20) DEFAULT 'publico'::character varying NOT NULL,
    ocr_texto text,
    check_out_por bigint,
    check_out_em timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    qr_code_token uuid,
    ug_id bigint,
    sistema_origem character varying(50),
    numero_externo character varying(100),
    metadados_externos json,
    callback_url character varying(500),
    callback_executado boolean DEFAULT false NOT NULL,
    callback_executado_em timestamp(0) without time zone
);


--
-- Name: ged_documentos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ged_documentos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ged_documentos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ged_documentos_id_seq OWNED BY public.ged_documentos.id;


--
-- Name: ged_favoritos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_favoritos (
    user_id bigint NOT NULL,
    documento_id bigint NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: ged_fluxo_etapas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_fluxo_etapas (
    id bigint NOT NULL,
    instancia_id bigint NOT NULL,
    nome character varying(100) NOT NULL,
    tipo character varying(20) NOT NULL,
    ordem integer DEFAULT 0 NOT NULL,
    responsavel_id bigint,
    status character varying(20) DEFAULT 'pendente'::character varying NOT NULL,
    prazo timestamp(0) without time zone,
    comentario text,
    concluido_em timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ged_fluxo_etapas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ged_fluxo_etapas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ged_fluxo_etapas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ged_fluxo_etapas_id_seq OWNED BY public.ged_fluxo_etapas.id;


--
-- Name: ged_fluxo_instancias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_fluxo_instancias (
    id bigint NOT NULL,
    fluxo_id bigint NOT NULL,
    documento_id bigint NOT NULL,
    status character varying(20) DEFAULT 'pendente'::character varying NOT NULL,
    etapa_atual character varying(100),
    iniciado_por bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ged_fluxo_instancias_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ged_fluxo_instancias_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ged_fluxo_instancias_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ged_fluxo_instancias_id_seq OWNED BY public.ged_fluxo_instancias.id;


--
-- Name: ged_fluxos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_fluxos (
    id bigint NOT NULL,
    nome character varying(100) NOT NULL,
    descricao text,
    definicao jsonb NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    criado_por bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ged_fluxos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ged_fluxos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ged_fluxos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ged_fluxos_id_seq OWNED BY public.ged_fluxos.id;


--
-- Name: ged_metadados; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_metadados (
    id bigint NOT NULL,
    documento_id bigint NOT NULL,
    chave character varying(100) NOT NULL,
    valor text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ged_metadados_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ged_metadados_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ged_metadados_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ged_metadados_id_seq OWNED BY public.ged_metadados.id;


--
-- Name: ged_notificacoes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_notificacoes (
    id bigint NOT NULL,
    usuario_id bigint NOT NULL,
    tipo character varying(50) NOT NULL,
    titulo character varying(255) NOT NULL,
    mensagem text,
    referencia_tipo character varying(50),
    referencia_id bigint,
    lida boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ged_notificacoes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ged_notificacoes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ged_notificacoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ged_notificacoes_id_seq OWNED BY public.ged_notificacoes.id;


--
-- Name: ged_pastas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_pastas (
    id bigint NOT NULL,
    nome character varying(255) NOT NULL,
    descricao text,
    parent_id bigint,
    path text NOT NULL,
    criado_por bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    ativo boolean DEFAULT true NOT NULL,
    ug_id bigint
);


--
-- Name: ged_pastas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ged_pastas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ged_pastas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ged_pastas_id_seq OWNED BY public.ged_pastas.id;


--
-- Name: ged_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_permissions (
    id bigint NOT NULL,
    nome character varying(100) NOT NULL,
    descricao text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ged_permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ged_permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ged_permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ged_permissions_id_seq OWNED BY public.ged_permissions.id;


--
-- Name: ged_role_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_role_permissions (
    role_id bigint NOT NULL,
    permission_id bigint NOT NULL
);


--
-- Name: ged_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_roles (
    id bigint NOT NULL,
    nome character varying(50) NOT NULL,
    descricao text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ged_roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ged_roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ged_roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ged_roles_id_seq OWNED BY public.ged_roles.id;


--
-- Name: ged_sistemas_integrados; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_sistemas_integrados (
    id bigint NOT NULL,
    codigo character varying(50) NOT NULL,
    nome character varying(200) NOT NULL,
    descricao text,
    api_token_hash character varying(255) NOT NULL,
    api_token_prefix character varying(12) NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    ultimo_uso_em timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    webhook_secret character varying(255),
    eventos_assinatura json
);


--
-- Name: ged_sistemas_integrados_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ged_sistemas_integrados_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ged_sistemas_integrados_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ged_sistemas_integrados_id_seq OWNED BY public.ged_sistemas_integrados.id;


--
-- Name: ged_solicitacoes_assinatura; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_solicitacoes_assinatura (
    id bigint NOT NULL,
    documento_id bigint NOT NULL,
    solicitante_id bigint NOT NULL,
    status character varying(20) DEFAULT 'pendente'::character varying NOT NULL,
    mensagem text,
    prazo timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ged_solicitacoes_assinatura_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ged_solicitacoes_assinatura_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ged_solicitacoes_assinatura_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ged_solicitacoes_assinatura_id_seq OWNED BY public.ged_solicitacoes_assinatura.id;


--
-- Name: ged_tags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_tags (
    id bigint NOT NULL,
    nome character varying(50) NOT NULL,
    cor character varying(7),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ged_tags_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ged_tags_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ged_tags_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ged_tags_id_seq OWNED BY public.ged_tags.id;


--
-- Name: ged_tipos_documentais; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_tipos_documentais (
    id bigint NOT NULL,
    nome character varying(100) NOT NULL,
    descricao text,
    schema_metadados jsonb,
    ativo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    sistema_origem character varying(50)
);


--
-- Name: ged_tipos_documentais_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ged_tipos_documentais_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ged_tipos_documentais_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ged_tipos_documentais_id_seq OWNED BY public.ged_tipos_documentais.id;


--
-- Name: ged_user_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_user_roles (
    user_id bigint NOT NULL,
    role_id bigint NOT NULL
);


--
-- Name: ged_versoes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_versoes (
    id bigint NOT NULL,
    documento_id bigint NOT NULL,
    versao integer NOT NULL,
    arquivo_path character varying(500) NOT NULL,
    tamanho bigint NOT NULL,
    hash_sha256 character varying(64),
    autor_id bigint NOT NULL,
    comentario text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ged_versoes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ged_versoes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ged_versoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ged_versoes_id_seq OWNED BY public.ged_versoes.id;


--
-- Name: ged_webhook_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ged_webhook_logs (
    id bigint NOT NULL,
    sistema_origem character varying(50) NOT NULL,
    documento_id bigint NOT NULL,
    evento character varying(50) NOT NULL,
    callback_url character varying(500) NOT NULL,
    payload json NOT NULL,
    signature_header character varying(100),
    sucesso boolean DEFAULT false NOT NULL,
    http_status integer,
    response_body text,
    erro text,
    tentativas integer DEFAULT 1 NOT NULL,
    duracao_ms integer,
    enviado_em timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ged_webhook_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ged_webhook_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ged_webhook_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ged_webhook_logs_id_seq OWNED BY public.ged_webhook_logs.id;


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
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: portal_banners; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.portal_banners (
    id bigint NOT NULL,
    ug_id bigint NOT NULL,
    imagem_path character varying(255) NOT NULL,
    titulo character varying(200),
    subtitulo text,
    link_url character varying(500),
    link_label character varying(60),
    ordem integer DEFAULT 0 NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: portal_banners_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.portal_banners_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: portal_banners_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.portal_banners_id_seq OWNED BY public.portal_banners.id;


--
-- Name: portal_categorias_servicos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.portal_categorias_servicos (
    id bigint NOT NULL,
    ug_id bigint NOT NULL,
    nome character varying(120) NOT NULL,
    slug character varying(140) NOT NULL,
    icone character varying(60),
    cor character varying(20),
    descricao text,
    ordem integer DEFAULT 0 NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: portal_categorias_servicos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.portal_categorias_servicos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: portal_categorias_servicos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.portal_categorias_servicos_id_seq OWNED BY public.portal_categorias_servicos.id;


--
-- Name: portal_cidadaos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.portal_cidadaos (
    id bigint NOT NULL,
    nome character varying(200) NOT NULL,
    email character varying(150) NOT NULL,
    cpf character varying(14),
    telefone character varying(30),
    senha character varying(255) NOT NULL,
    email_verificado_em timestamp(0) without time zone,
    token_verificacao character varying(64),
    ativo boolean DEFAULT true NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: portal_cidadaos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.portal_cidadaos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: portal_cidadaos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.portal_cidadaos_id_seq OWNED BY public.portal_cidadaos.id;


--
-- Name: portal_servicos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.portal_servicos (
    id bigint NOT NULL,
    ug_id bigint NOT NULL,
    categoria_id bigint,
    titulo character varying(255) NOT NULL,
    slug character varying(200) NOT NULL,
    publico_alvo character varying(20) DEFAULT 'cidadao'::character varying NOT NULL,
    descricao_curta text,
    descricao_completa text,
    requisitos text,
    documentos_necessarios json,
    prazo_entrega character varying(255),
    custo character varying(255),
    canais json,
    orgao_responsavel character varying(255),
    legislacao text,
    palavras_chave json,
    icone character varying(60),
    publicado boolean DEFAULT false NOT NULL,
    visualizacoes integer DEFAULT 0 NOT NULL,
    ordem integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    permite_anonimo boolean DEFAULT false NOT NULL,
    setor_responsavel_id bigint,
    tipo_processo_id bigint
);


--
-- Name: portal_servicos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.portal_servicos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: portal_servicos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.portal_servicos_id_seq OWNED BY public.portal_servicos.id;


--
-- Name: portal_solicitacao_eventos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.portal_solicitacao_eventos (
    id bigint NOT NULL,
    solicitacao_id bigint NOT NULL,
    tipo character varying(30) NOT NULL,
    autor_tipo character varying(20) NOT NULL,
    autor_nome character varying(255),
    autor_user_id bigint,
    autor_cidadao_id bigint,
    status_anterior character varying(20),
    status_novo character varying(20),
    mensagem text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: portal_solicitacao_eventos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.portal_solicitacao_eventos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: portal_solicitacao_eventos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.portal_solicitacao_eventos_id_seq OWNED BY public.portal_solicitacao_eventos.id;


--
-- Name: portal_solicitacoes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.portal_solicitacoes (
    id bigint NOT NULL,
    codigo character varying(30) NOT NULL,
    ug_id bigint NOT NULL,
    servico_id bigint NOT NULL,
    cidadao_id bigint,
    status character varying(20) DEFAULT 'aberta'::character varying NOT NULL,
    descricao text NOT NULL,
    telefone_contato character varying(30),
    email_contato character varying(150),
    atendente_id bigint,
    resposta text,
    respondida_em timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    anonima boolean DEFAULT false NOT NULL,
    processo_id bigint
);


--
-- Name: portal_solicitacoes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.portal_solicitacoes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: portal_solicitacoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.portal_solicitacoes_id_seq OWNED BY public.portal_solicitacoes.id;


--
-- Name: proc_anexos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proc_anexos (
    id bigint NOT NULL,
    processo_id bigint NOT NULL,
    tramitacao_id bigint,
    nome character varying(255) NOT NULL,
    arquivo_path character varying(500) NOT NULL,
    tamanho bigint NOT NULL,
    mime_type character varying(100) NOT NULL,
    hash_sha256 character varying(64),
    enviado_por bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: proc_anexos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proc_anexos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proc_anexos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proc_anexos_id_seq OWNED BY public.proc_anexos.id;


--
-- Name: proc_circular_anexos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proc_circular_anexos (
    id bigint NOT NULL,
    circular_id bigint NOT NULL,
    nome character varying(255) NOT NULL,
    arquivo_path character varying(500) NOT NULL,
    tamanho bigint NOT NULL,
    mime_type character varying(100) NOT NULL,
    enviado_por bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: proc_circular_anexos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proc_circular_anexos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proc_circular_anexos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proc_circular_anexos_id_seq OWNED BY public.proc_circular_anexos.id;


--
-- Name: proc_circular_destinatarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proc_circular_destinatarios (
    id bigint NOT NULL,
    circular_id bigint NOT NULL,
    usuario_id bigint NOT NULL,
    lido boolean DEFAULT false NOT NULL,
    lido_em timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: proc_circular_destinatarios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proc_circular_destinatarios_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proc_circular_destinatarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proc_circular_destinatarios_id_seq OWNED BY public.proc_circular_destinatarios.id;


--
-- Name: proc_circulares; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proc_circulares (
    id bigint NOT NULL,
    numero character varying(30) NOT NULL,
    assunto character varying(500) NOT NULL,
    conteudo text NOT NULL,
    remetente_id bigint NOT NULL,
    setor_origem character varying(150),
    destino_tipo character varying(20) DEFAULT 'todos'::character varying NOT NULL,
    destino_setores jsonb,
    status character varying(20) DEFAULT 'rascunho'::character varying NOT NULL,
    enviado_em timestamp(0) without time zone,
    arquivado_em timestamp(0) without time zone,
    data_arquivamento_auto date,
    qr_code_token uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    ug_id bigint,
    documento_id bigint
);


--
-- Name: proc_circulares_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proc_circulares_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proc_circulares_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proc_circulares_id_seq OWNED BY public.proc_circulares.id;


--
-- Name: proc_comentarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proc_comentarios (
    id bigint NOT NULL,
    processo_id bigint NOT NULL,
    tramitacao_id bigint,
    usuario_id bigint NOT NULL,
    texto text NOT NULL,
    interno boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: proc_comentarios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proc_comentarios_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proc_comentarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proc_comentarios_id_seq OWNED BY public.proc_comentarios.id;


--
-- Name: proc_historico; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proc_historico (
    id bigint NOT NULL,
    processo_id bigint NOT NULL,
    usuario_id bigint,
    acao character varying(50) NOT NULL,
    detalhes jsonb,
    ip character varying(45),
    user_agent text,
    created_at timestamp(0) without time zone
);


--
-- Name: proc_historico_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proc_historico_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proc_historico_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proc_historico_id_seq OWNED BY public.proc_historico.id;


--
-- Name: proc_memorando_destinatarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proc_memorando_destinatarios (
    id bigint NOT NULL,
    memorando_id bigint NOT NULL,
    usuario_id bigint,
    setor_destino character varying(150),
    lido boolean DEFAULT false NOT NULL,
    lido_em timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    unidade_id bigint
);


--
-- Name: proc_memorando_tramitacoes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proc_memorando_tramitacoes (
    id bigint NOT NULL,
    memorando_id bigint NOT NULL,
    tramite_origem_id bigint,
    origem_usuario_id bigint NOT NULL,
    origem_unidade_id bigint,
    destino_usuario_id bigint,
    destino_unidade_id bigint,
    parecer text,
    em_uso boolean DEFAULT true NOT NULL,
    finalizado boolean DEFAULT false NOT NULL,
    despachado_em timestamp(0) without time zone,
    recebido_em timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: proc_memorandos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proc_memorandos (
    id bigint NOT NULL,
    numero character varying(30) NOT NULL,
    assunto character varying(500) NOT NULL,
    conteudo text NOT NULL,
    remetente_id bigint NOT NULL,
    setor_origem character varying(150),
    confidencial boolean DEFAULT false NOT NULL,
    status character varying(20) DEFAULT 'rascunho'::character varying NOT NULL,
    enviado_em timestamp(0) without time zone,
    arquivado_em timestamp(0) without time zone,
    data_arquivamento_auto date,
    qr_code_token uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    ug_id bigint,
    documento_id bigint
);


--
-- Name: proc_oficios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proc_oficios (
    id bigint NOT NULL,
    numero character varying(30) NOT NULL,
    assunto character varying(500) NOT NULL,
    conteudo text NOT NULL,
    remetente_id bigint NOT NULL,
    setor_origem character varying(150),
    destinatario_nome character varying(255) NOT NULL,
    destinatario_email character varying(255) NOT NULL,
    destinatario_cargo character varying(150),
    destinatario_orgao character varying(255),
    status character varying(20) DEFAULT 'rascunho'::character varying NOT NULL,
    enviado_em timestamp(0) without time zone,
    entregue_em timestamp(0) without time zone,
    lido_em timestamp(0) without time zone,
    arquivado_em timestamp(0) without time zone,
    rastreio_token character varying(64),
    qr_code_token uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    ug_id bigint,
    destinatario_usuario_id bigint,
    destinatario_unidade_id bigint,
    lido_em_interno timestamp(0) without time zone,
    documento_id bigint
);


--
-- Name: proc_processos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proc_processos (
    id bigint NOT NULL,
    numero_protocolo character varying(30) NOT NULL,
    tipo_processo_id bigint NOT NULL,
    assunto character varying(500) NOT NULL,
    descricao text,
    dados_formulario jsonb,
    requerente_nome character varying(255),
    requerente_cpf character varying(14),
    requerente_email character varying(255),
    requerente_telefone character varying(20),
    setor_origem character varying(150),
    etapa_atual_id bigint,
    status character varying(30) DEFAULT 'aberto'::character varying NOT NULL,
    prioridade character varying(20) DEFAULT 'normal'::character varying NOT NULL,
    aberto_por bigint,
    concluido_por bigint,
    concluido_em timestamp(0) without time zone,
    observacao_conclusao text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    ug_id bigint,
    decisao character varying(50),
    solicitacao_assinatura_id bigint,
    documento_decisao_id bigint
);


--
-- Name: proc_tramitacoes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proc_tramitacoes (
    id bigint NOT NULL,
    processo_id bigint NOT NULL,
    tipo_etapa_id bigint,
    ordem integer NOT NULL,
    setor_origem character varying(150),
    setor_destino character varying(150) NOT NULL,
    remetente_id bigint,
    destinatario_id bigint,
    recebido_por bigint,
    status character varying(30) DEFAULT 'pendente'::character varying NOT NULL,
    despacho text,
    parecer text,
    sla_horas integer,
    prazo timestamp(0) without time zone,
    recebido_em timestamp(0) without time zone,
    despachado_em timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    destino_unidade_id bigint,
    lida_em timestamp(0) without time zone
);


--
-- Name: proc_inbox; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.proc_inbox AS
 SELECT ((('M-'::text || m.id) || '-'::text) || d.id) AS id,
    'memorando'::text AS tipo,
    m.id AS item_id,
    m.numero,
    m.assunto,
    m.remetente_id,
    d.usuario_id AS destino_usuario_id,
    d.unidade_id AS destino_unidade_id,
    m.status,
    m.confidencial,
    d.lido,
    d.lido_em,
    m.created_at AS criado_em,
    m.enviado_em,
    m.arquivado_em,
    m.ug_id
   FROM (public.proc_memorandos m
     JOIN public.proc_memorando_destinatarios d ON ((d.memorando_id = m.id)))
  WHERE ((m.deleted_at IS NULL) AND (NOT (EXISTS ( SELECT 1
           FROM public.proc_memorando_tramitacoes t
          WHERE (t.memorando_id = m.id)))))
UNION ALL
 SELECT ((('MT-'::text || m.id) || '-'::text) || t.id) AS id,
    'memorando'::text AS tipo,
    m.id AS item_id,
    m.numero,
    m.assunto,
    t.origem_usuario_id AS remetente_id,
    t.destino_usuario_id,
    t.destino_unidade_id,
    m.status,
    m.confidencial,
    t.finalizado AS lido,
    t.recebido_em AS lido_em,
    t.created_at AS criado_em,
    t.despachado_em AS enviado_em,
    m.arquivado_em,
    m.ug_id
   FROM (public.proc_memorandos m
     JOIN public.proc_memorando_tramitacoes t ON ((t.memorando_id = m.id)))
  WHERE ((m.deleted_at IS NULL) AND (t.em_uso = true))
UNION ALL
 SELECT ('O-'::text || o.id) AS id,
    'oficio'::text AS tipo,
    o.id AS item_id,
    o.numero,
    o.assunto,
    o.remetente_id,
    o.destinatario_usuario_id AS destino_usuario_id,
    o.destinatario_unidade_id AS destino_unidade_id,
    o.status,
    false AS confidencial,
    (o.lido_em_interno IS NOT NULL) AS lido,
    o.lido_em_interno AS lido_em,
    o.created_at AS criado_em,
    o.enviado_em,
    o.arquivado_em,
    o.ug_id
   FROM public.proc_oficios o
  WHERE ((o.deleted_at IS NULL) AND ((o.destinatario_usuario_id IS NOT NULL) OR (o.destinatario_unidade_id IS NOT NULL)))
UNION ALL
 SELECT ((('P-'::text || p.id) || '-'::text) || t.id) AS id,
    'processo'::text AS tipo,
    p.id AS item_id,
    p.numero_protocolo AS numero,
    p.assunto,
    t.remetente_id,
    t.destinatario_id AS destino_usuario_id,
    t.destino_unidade_id,
    p.status,
    false AS confidencial,
    (t.lida_em IS NOT NULL) AS lido,
    t.lida_em AS lido_em,
    t.created_at AS criado_em,
    t.despachado_em AS enviado_em,
    NULL::timestamp without time zone AS arquivado_em,
    p.ug_id
   FROM (public.proc_processos p
     JOIN public.proc_tramitacoes t ON ((t.id = ( SELECT max(t2.id) AS max
           FROM public.proc_tramitacoes t2
          WHERE (t2.processo_id = p.id)))))
  WHERE (p.deleted_at IS NULL);


--
-- Name: proc_memorando_anexos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proc_memorando_anexos (
    id bigint NOT NULL,
    memorando_id bigint NOT NULL,
    nome character varying(255) NOT NULL,
    arquivo_path character varying(500) NOT NULL,
    tamanho bigint NOT NULL,
    mime_type character varying(100) NOT NULL,
    enviado_por bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: proc_memorando_anexos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proc_memorando_anexos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proc_memorando_anexos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proc_memorando_anexos_id_seq OWNED BY public.proc_memorando_anexos.id;


--
-- Name: proc_memorando_destinatarios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proc_memorando_destinatarios_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proc_memorando_destinatarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proc_memorando_destinatarios_id_seq OWNED BY public.proc_memorando_destinatarios.id;


--
-- Name: proc_memorando_respostas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proc_memorando_respostas (
    id bigint NOT NULL,
    memorando_id bigint NOT NULL,
    usuario_id bigint NOT NULL,
    conteudo text NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: proc_memorando_respostas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proc_memorando_respostas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proc_memorando_respostas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proc_memorando_respostas_id_seq OWNED BY public.proc_memorando_respostas.id;


--
-- Name: proc_memorando_tramitacoes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proc_memorando_tramitacoes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proc_memorando_tramitacoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proc_memorando_tramitacoes_id_seq OWNED BY public.proc_memorando_tramitacoes.id;


--
-- Name: proc_memorandos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proc_memorandos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proc_memorandos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proc_memorandos_id_seq OWNED BY public.proc_memorandos.id;


--
-- Name: proc_oficio_anexos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proc_oficio_anexos (
    id bigint NOT NULL,
    oficio_id bigint NOT NULL,
    nome character varying(255) NOT NULL,
    arquivo_path character varying(500) NOT NULL,
    tamanho bigint NOT NULL,
    mime_type character varying(100) NOT NULL,
    solicitar_assinatura boolean DEFAULT false NOT NULL,
    enviado_por bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: proc_oficio_anexos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proc_oficio_anexos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proc_oficio_anexos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proc_oficio_anexos_id_seq OWNED BY public.proc_oficio_anexos.id;


--
-- Name: proc_oficio_respostas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proc_oficio_respostas (
    id bigint NOT NULL,
    oficio_id bigint NOT NULL,
    respondente_nome character varying(255),
    respondente_email character varying(255),
    conteudo text NOT NULL,
    externo boolean DEFAULT false NOT NULL,
    usuario_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: proc_oficio_respostas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proc_oficio_respostas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proc_oficio_respostas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proc_oficio_respostas_id_seq OWNED BY public.proc_oficio_respostas.id;


--
-- Name: proc_oficios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proc_oficios_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proc_oficios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proc_oficios_id_seq OWNED BY public.proc_oficios.id;


--
-- Name: proc_processos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proc_processos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proc_processos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proc_processos_id_seq OWNED BY public.proc_processos.id;


--
-- Name: proc_tipo_etapas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proc_tipo_etapas (
    id bigint NOT NULL,
    tipo_processo_id bigint NOT NULL,
    nome character varying(150) NOT NULL,
    descricao text,
    ordem integer NOT NULL,
    tipo character varying(30) DEFAULT 'analise'::character varying NOT NULL,
    setor_destino character varying(150),
    responsavel_id bigint,
    sla_horas integer,
    template_texto text,
    obrigatorio boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: proc_tipo_etapas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proc_tipo_etapas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proc_tipo_etapas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proc_tipo_etapas_id_seq OWNED BY public.proc_tipo_etapas.id;


--
-- Name: proc_tipos_processo; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proc_tipos_processo (
    id bigint NOT NULL,
    nome character varying(150) NOT NULL,
    descricao text,
    sigla character varying(10) NOT NULL,
    categoria character varying(50) DEFAULT 'administrativo'::character varying NOT NULL,
    schema_formulario jsonb,
    templates_despacho jsonb,
    sla_padrao_horas integer DEFAULT 72 NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    criado_por bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: proc_tipos_processo_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proc_tipos_processo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proc_tipos_processo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proc_tipos_processo_id_seq OWNED BY public.proc_tipos_processo.id;


--
-- Name: proc_tramitacoes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proc_tramitacoes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proc_tramitacoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proc_tramitacoes_id_seq OWNED BY public.proc_tramitacoes.id;


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
-- Name: ug_organograma; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ug_organograma (
    id bigint NOT NULL,
    ug_id bigint NOT NULL,
    parent_id bigint,
    nivel smallint NOT NULL,
    codigo character varying(20),
    nome character varying(200) NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    endereco_proprio boolean DEFAULT false NOT NULL,
    cep character varying(9),
    logradouro character varying(200),
    numero character varying(20),
    complemento character varying(100),
    bairro character varying(100),
    cidade character varying(100),
    uf character(2),
    legado_id bigint,
    legado_tipo character varying(20),
    dt_inicio date,
    dt_encerramento date,
    tipo_orgao character varying(50),
    tipo_fundo character varying(50),
    codigo_tce character varying(20),
    suprimir_tce boolean DEFAULT false NOT NULL,
    responsavel_id bigint,
    protocolo_externo boolean DEFAULT false NOT NULL
);


--
-- Name: ug_organograma_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ug_organograma_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ug_organograma_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ug_organograma_id_seq OWNED BY public.ug_organograma.id;


--
-- Name: ugs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ugs (
    id bigint NOT NULL,
    codigo character varying(20) NOT NULL,
    nome character varying(200) NOT NULL,
    cnpj character varying(18),
    nivel_1_label character varying(60) DEFAULT 'Órgão'::character varying NOT NULL,
    nivel_2_label character varying(60) DEFAULT 'Unidade'::character varying NOT NULL,
    nivel_3_label character varying(60) DEFAULT 'Setor'::character varying NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    observacoes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    cep character varying(9),
    logradouro character varying(200),
    numero character varying(20),
    complemento character varying(100),
    bairro character varying(100),
    cidade character varying(100),
    uf character(2),
    legado_orgao_id bigint,
    brasao_path character varying(255),
    telefone character varying(50),
    email_institucional character varying(150),
    site character varying(150),
    portal_slug character varying(80),
    banner_path character varying(255),
    banner_titulo character varying(200),
    banner_subtitulo text,
    banner_link_url character varying(500),
    banner_link_label character varying(60),
    banner_ativo boolean DEFAULT true NOT NULL
);


--
-- Name: ugs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ugs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ugs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ugs_id_seq OWNED BY public.ugs.id;


--
-- Name: user_ugs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_ugs (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    ug_id bigint NOT NULL,
    principal boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: user_ugs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_ugs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_ugs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_ugs_id_seq OWNED BY public.user_ugs.id;


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
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    cpf character varying(14),
    tipo character varying(10) DEFAULT 'interno'::character varying NOT NULL,
    ug_id bigint,
    unidade_id bigint,
    legado_usuario_id bigint,
    super_admin boolean DEFAULT false NOT NULL,
    acesso_geral_ug boolean DEFAULT false NOT NULL
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
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: ged_assinaturas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_assinaturas ALTER COLUMN id SET DEFAULT nextval('public.ged_assinaturas_id_seq'::regclass);


--
-- Name: ged_audit_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_audit_logs ALTER COLUMN id SET DEFAULT nextval('public.ged_audit_logs_id_seq'::regclass);


--
-- Name: ged_buscas_salvas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_buscas_salvas ALTER COLUMN id SET DEFAULT nextval('public.ged_buscas_salvas_id_seq'::regclass);


--
-- Name: ged_certificados id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_certificados ALTER COLUMN id SET DEFAULT nextval('public.ged_certificados_id_seq'::regclass);


--
-- Name: ged_compartilhamentos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_compartilhamentos ALTER COLUMN id SET DEFAULT nextval('public.ged_compartilhamentos_id_seq'::regclass);


--
-- Name: ged_documentos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_documentos ALTER COLUMN id SET DEFAULT nextval('public.ged_documentos_id_seq'::regclass);


--
-- Name: ged_fluxo_etapas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_fluxo_etapas ALTER COLUMN id SET DEFAULT nextval('public.ged_fluxo_etapas_id_seq'::regclass);


--
-- Name: ged_fluxo_instancias id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_fluxo_instancias ALTER COLUMN id SET DEFAULT nextval('public.ged_fluxo_instancias_id_seq'::regclass);


--
-- Name: ged_fluxos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_fluxos ALTER COLUMN id SET DEFAULT nextval('public.ged_fluxos_id_seq'::regclass);


--
-- Name: ged_metadados id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_metadados ALTER COLUMN id SET DEFAULT nextval('public.ged_metadados_id_seq'::regclass);


--
-- Name: ged_notificacoes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_notificacoes ALTER COLUMN id SET DEFAULT nextval('public.ged_notificacoes_id_seq'::regclass);


--
-- Name: ged_pastas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_pastas ALTER COLUMN id SET DEFAULT nextval('public.ged_pastas_id_seq'::regclass);


--
-- Name: ged_permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_permissions ALTER COLUMN id SET DEFAULT nextval('public.ged_permissions_id_seq'::regclass);


--
-- Name: ged_roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_roles ALTER COLUMN id SET DEFAULT nextval('public.ged_roles_id_seq'::regclass);


--
-- Name: ged_sistemas_integrados id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_sistemas_integrados ALTER COLUMN id SET DEFAULT nextval('public.ged_sistemas_integrados_id_seq'::regclass);


--
-- Name: ged_solicitacoes_assinatura id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_solicitacoes_assinatura ALTER COLUMN id SET DEFAULT nextval('public.ged_solicitacoes_assinatura_id_seq'::regclass);


--
-- Name: ged_tags id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_tags ALTER COLUMN id SET DEFAULT nextval('public.ged_tags_id_seq'::regclass);


--
-- Name: ged_tipos_documentais id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_tipos_documentais ALTER COLUMN id SET DEFAULT nextval('public.ged_tipos_documentais_id_seq'::regclass);


--
-- Name: ged_versoes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_versoes ALTER COLUMN id SET DEFAULT nextval('public.ged_versoes_id_seq'::regclass);


--
-- Name: ged_webhook_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_webhook_logs ALTER COLUMN id SET DEFAULT nextval('public.ged_webhook_logs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: portal_banners id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_banners ALTER COLUMN id SET DEFAULT nextval('public.portal_banners_id_seq'::regclass);


--
-- Name: portal_categorias_servicos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_categorias_servicos ALTER COLUMN id SET DEFAULT nextval('public.portal_categorias_servicos_id_seq'::regclass);


--
-- Name: portal_cidadaos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_cidadaos ALTER COLUMN id SET DEFAULT nextval('public.portal_cidadaos_id_seq'::regclass);


--
-- Name: portal_servicos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_servicos ALTER COLUMN id SET DEFAULT nextval('public.portal_servicos_id_seq'::regclass);


--
-- Name: portal_solicitacao_eventos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_solicitacao_eventos ALTER COLUMN id SET DEFAULT nextval('public.portal_solicitacao_eventos_id_seq'::regclass);


--
-- Name: portal_solicitacoes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_solicitacoes ALTER COLUMN id SET DEFAULT nextval('public.portal_solicitacoes_id_seq'::regclass);


--
-- Name: proc_anexos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_anexos ALTER COLUMN id SET DEFAULT nextval('public.proc_anexos_id_seq'::regclass);


--
-- Name: proc_circular_anexos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_circular_anexos ALTER COLUMN id SET DEFAULT nextval('public.proc_circular_anexos_id_seq'::regclass);


--
-- Name: proc_circular_destinatarios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_circular_destinatarios ALTER COLUMN id SET DEFAULT nextval('public.proc_circular_destinatarios_id_seq'::regclass);


--
-- Name: proc_circulares id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_circulares ALTER COLUMN id SET DEFAULT nextval('public.proc_circulares_id_seq'::regclass);


--
-- Name: proc_comentarios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_comentarios ALTER COLUMN id SET DEFAULT nextval('public.proc_comentarios_id_seq'::regclass);


--
-- Name: proc_historico id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_historico ALTER COLUMN id SET DEFAULT nextval('public.proc_historico_id_seq'::regclass);


--
-- Name: proc_memorando_anexos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_anexos ALTER COLUMN id SET DEFAULT nextval('public.proc_memorando_anexos_id_seq'::regclass);


--
-- Name: proc_memorando_destinatarios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_destinatarios ALTER COLUMN id SET DEFAULT nextval('public.proc_memorando_destinatarios_id_seq'::regclass);


--
-- Name: proc_memorando_respostas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_respostas ALTER COLUMN id SET DEFAULT nextval('public.proc_memorando_respostas_id_seq'::regclass);


--
-- Name: proc_memorando_tramitacoes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_tramitacoes ALTER COLUMN id SET DEFAULT nextval('public.proc_memorando_tramitacoes_id_seq'::regclass);


--
-- Name: proc_memorandos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorandos ALTER COLUMN id SET DEFAULT nextval('public.proc_memorandos_id_seq'::regclass);


--
-- Name: proc_oficio_anexos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_oficio_anexos ALTER COLUMN id SET DEFAULT nextval('public.proc_oficio_anexos_id_seq'::regclass);


--
-- Name: proc_oficio_respostas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_oficio_respostas ALTER COLUMN id SET DEFAULT nextval('public.proc_oficio_respostas_id_seq'::regclass);


--
-- Name: proc_oficios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_oficios ALTER COLUMN id SET DEFAULT nextval('public.proc_oficios_id_seq'::regclass);


--
-- Name: proc_processos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_processos ALTER COLUMN id SET DEFAULT nextval('public.proc_processos_id_seq'::regclass);


--
-- Name: proc_tipo_etapas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_tipo_etapas ALTER COLUMN id SET DEFAULT nextval('public.proc_tipo_etapas_id_seq'::regclass);


--
-- Name: proc_tipos_processo id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_tipos_processo ALTER COLUMN id SET DEFAULT nextval('public.proc_tipos_processo_id_seq'::regclass);


--
-- Name: proc_tramitacoes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_tramitacoes ALTER COLUMN id SET DEFAULT nextval('public.proc_tramitacoes_id_seq'::regclass);


--
-- Name: ug_organograma id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ug_organograma ALTER COLUMN id SET DEFAULT nextval('public.ug_organograma_id_seq'::regclass);


--
-- Name: ugs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ugs ALTER COLUMN id SET DEFAULT nextval('public.ugs_id_seq'::regclass);


--
-- Name: user_ugs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_ugs ALTER COLUMN id SET DEFAULT nextval('public.user_ugs_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: ged_certificados; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_certificados (id, user_id, tipo, subject_cn, subject_cpf, subject_dn, issuer_cn, issuer_dn, serial_number, thumbprint_sha1, thumbprint_sha256, valido_de, valido_ate, certificado_pem, cadeia_pem, politica_oid, icp_brasil, revogado, verificado_em, created_at, updated_at) FROM stdin;
1	94	A1	JOEL GONCALVES JARDIM:85183865604	85183865604	C=BR, O=ICP-Brasil, OU=Certificado Digital PF A1,Videoconferencia,32522131000125,AC SyngularID Multipla, CN=JOEL GONCALVES JARDIM:85183865604	AC SyngularID Multipla	C=BR, OU=AC SyngularID, O=ICP-Brasil, CN=AC SyngularID Multipla	B6FB0F469F3BA3A40727	f4803af5fd112347f1480abde8c2590172f04abd	3476aaa7686efb70f798fac92e7aa7a2a02c89f73dd794b054e26300323c02fa	2026-04-30 16:54:00	2027-04-30 16:54:00	-----BEGIN CERTIFICATE-----\nMIIHyjCCBbKgAwIBAgILALb7D0afO6OkBycwDQYJKoZIhvcNAQELBQAwWzELMAkG\nA1UEBhMCQlIxFjAUBgNVBAsMDUFDIFN5bmd1bGFySUQxEzARBgNVBAoMCklDUC1C\ncmFzaWwxHzAdBgNVBAMMFkFDIFN5bmd1bGFySUQgTXVsdGlwbGEwHhcNMjYwNDMw\nMTY1NDAwWhcNMjcwNDMwMTY1NDAwWjCBxzELMAkGA1UEBhMCQlIxEzARBgNVBAoM\nCklDUC1CcmFzaWwxIjAgBgNVBAsMGUNlcnRpZmljYWRvIERpZ2l0YWwgUEYgQTEx\nGTAXBgNVBAsMEFZpZGVvY29uZmVyZW5jaWExFzAVBgNVBAsMDjMyNTIyMTMxMDAw\nMTI1MR8wHQYDVQQLDBZBQyBTeW5ndWxhcklEIE11bHRpcGxhMSowKAYDVQQDDCFK\nT0VMIEdPTkNBTFZFUyBKQVJESU06ODUxODM4NjU2MDQwggEiMA0GCSqGSIb3DQEB\nAQUAA4IBDwAwggEKAoIBAQClLn9Z7TlWRyC/5SFNowiTCq/4CdxguqKSNI25baWk\nBegj2lbkReCCZmIOVMO6STz1gY+5aQMddt4PgiDCz2cYyUqunXs1uOoRsRMedHy1\nbAmXFeyYdsiwyS6EJY+uTQLV46lB+sI0FwlliNfYncFKzV16KhPlT5Ir3YdEue2E\nfsUFE+ayKS/p/ABx+USI065kiFMaCY6KRLY9itHZd1k4wS9J9hpxfIpeVpx9Dqan\nGQLMgZ3XY6J1OuckkLoXcwB6HQbHkAvA7M9ATh64bli5rb+jWTsG8rCLyn1mNJEw\nji3/hAcVPCCdD52T7dY88fxK/WHXA1tDw95og/uqepcvAgMBAAGjggMgMIIDHDAO\nBgNVHQ8BAf8EBAMCBeAwHQYDVR0lBBYwFAYIKwYBBQUHAwQGCCsGAQUFBwMCMAkG\nA1UdEwQCMAAwHwYDVR0jBBgwFoAUk+H/fh3l9eRN4TliiyFpleavchYwHQYDVR0O\nBBYEFNHD9fVMKkEAj0TAH2QknrtDbeP4MH8GCCsGAQUFBwEBBHMwcTBvBggrBgEF\nBQcwAoZjaHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3JlcG9zaXRvcmlvL2FjLXN5\nbmd1bGFyaWQtbXVsdGlwbGEvY2VydGlmaWNhZG9zL2FjLXN5bmd1bGFyaWQtbXVs\ndGlwbGEucDdiMIGCBgNVHSAEezB5MHcGB2BMAQIBgQUwbDBqBggrBgEFBQcCARZe\naHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3JlcG9zaXRvcmlvL2FjLXN5bmd1bGFy\naWQtbXVsdGlwbGEvZHBjL2RwYy1hYy1zeW5ndWxhcklELW11bHRpcGxhLnBkZjCB\ntAYDVR0RBIGsMIGpoEIGBWBMAQMBoDkENzAzMTAxOTcyODUxODM4NjU2MDQwMDAw\nMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDCgNAYFYEwBAwWgKwQpMDAw\nMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDCgFwYFYEwBAwag\nDgQMMDAwMDAwMDAwMDAwgRRqb2VsamFyZGltQGdtYWlsLmNvbTCB4gYDVR0fBIHa\nMIHXMGSgYqBghl5odHRwOi8vc3luZ3VsYXJpZC5jb20uYnIvcmVwb3NpdG9yaW8v\nYWMtc3luZ3VsYXJpZC1tdWx0aXBsYS9sY3IvbGNyLWFjLXN5bmd1bGFyaWQtbXVs\ndGlwbGEuY3JsMG+gbaBrhmlodHRwOi8vaWNwLWJyYXNpbC5zeW5ndWxhcmlkLmNv\nbS5ici9yZXBvc2l0b3Jpby9hYy1zeW5ndWxhcmlkLW11bHRpcGxhL2xjci9sY3It\nYWMtc3luZ3VsYXJpZC1tdWx0aXBsYS5jcmwwDQYJKoZIhvcNAQELBQADggIBAAid\nwb043Ztl4RgQtGLwcnYpzFsq+koraPg0NYRXk96nBSgxTtX3aWTHKWQAMU/aOySL\nvjjE9b3nYNhbNeMDnlTfh/vfh/GvHKbkDcw4F1UoW8cgn6LHgN/ckLsO+3XonU8o\ndPsW8DJOGhol36En0Gn2A7jIbxi84Upi6vJmrNzKB8GeQSUv9aNCahpFOMpMPSfl\nsU7ciTMFx5wHa4CC7fSNrhCEOWbuwLHd/W0KyECHVscsWim2h7DyztICmjNREYAQ\nMSh5AIB2sKWeO7Fp/NPmwU7wEx8WkgCuvkiip1tJ1vFxwV3ziBrtNDqk6gNjFxh9\nNYREgwJfeMyzp+hT2IkAH0b2IHZN0PACVaWk98zg4SZUp8UklXEH1TYRjXj9/6/i\n9KWHCI+L/o5UcoiGYMqbUJUm2+36wqq6OtZ4NbvJpe9PJpVZ/VY01IsgRx0RZCRZ\n5eW3cN+axPOh5HtOEv+nfK9vuoSnbaYRc/BS3Rg46Wt+IlypzWTY0lRRJbDSMZrg\nB2NYAr8XwFnmJg4ZKETJ09kIogW59JKctf2dGXrHGTquSKosiz/ImIY8BAReUx6H\nbw05L8XjSeOfELPhTxMEY3KoFS0b93J1vW9t4dxg88R89lGLrvgEw9w3o/p1EbfK\nmC0wwhxtg0bvyv6Wwqznyr8K18tfv8WIkyuNqgMI\n-----END CERTIFICATE-----\n	["-----BEGIN CERTIFICATE-----\\nMIIGSDCCBDCgAwIBAgIJAOsvRfLjYt7QMA0GCSqGSIb3DQEBDQUAMIGXMQswCQYD\\nVQQGEwJCUjETMBEGA1UECgwKSUNQLUJyYXNpbDE9MDsGA1UECww0SW5zdGl0dXRv\\nIE5hY2lvbmFsIGRlIFRlY25vbG9naWEgZGEgSW5mb3JtYWNhbyAtIElUSTE0MDIG\\nA1UEAwwrQXV0b3JpZGFkZSBDZXJ0aWZpY2Fkb3JhIFJhaXogQnJhc2lsZWlyYSB2\\nNTAeFw0yMjAzMjExODAwMjFaFw0yOTAzMDIxMjAwMjFaMHAxCzAJBgNVBAYMAkJS\\nMRMwEQYDVQQKDApJQ1AtQnJhc2lsMTQwMgYDVQQLDCtBdXRvcmlkYWRlIENlcnRp\\nZmljYWRvcmEgUmFpeiBCcmFzaWxlaXJhIHY1MRYwFAYDVQQDDA1BQyBTeW5ndWxh\\ncklEMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAp+di0cX1UCcm8bp7\\nwt1Nz7quHmM7tGPH9ri6\\/6CmIP3hy3Ww4ivFxOJSYsUwoxJcs4SiA3oOr7IAr6VR\\nkYo1eohcE+ZQOEnE7Id5AD19dBeOmcgYfrH7kOKSrmFeaJXs+KCAXgd04WzqVRKM\\nMsoEIIps9SHUwwL4KSR3Ecyyd3mMlJPBtyoaPqBz+e8nY3i\\/JVFvVIoTxoBKJqJy\\nUtvS0l0baiZUPBtG3zd5es65OM4h2tYkrWDHvWoffHaQdIJC2ke4SIojX0ozTWae\\nMd0Az4USCKfb\\/MhFg2WtwalWKZOBKGiG8fpsKErq9gxoGpoLmqkvq3AlHk6bpkhf\\nnEs\\/LpdV0TfbeCs\\/weK63UoMXqSOMkIr8eO9mLjZoXg5xDiF5TW1EH1XFY2j91OD\\ngZ+xlOf6KO+\\/2NA26nJfHbeJwm0eRg5rgPZFCUdk9Lf9rUXEDqidZwQmofxa6OGN\\n9lr0Sed4HpQegeacyfbOrBB8MfcOMqUf9oZvimDlqXEQUaRkgN2DJ5Eqmw32AE1A\\nPdqQ\\/nt0brI6PVt4oEdcV06wb7X2aWSc2C3V582JqTv0g6I2901hesvfadVbOLA1\\n18uNodiu2lxji\\/e4mSaXcEEO4u30hNtU\\/6zXANOh0Jtf5X8lHczaNFqj2BT9AxdJ\\njlFWBZ2v7SwEcCTKLSFBLBxTN+UCAwEAAaOBvDCBuTAOBgNVHQ8BAf8EBAMCAQYw\\nFQYDVR0gBA4wDDAKBgZgTAEBgRwwADA\\/BgNVHR8EODA2MDSgMqAwhi5odHRwOi8v\\nYWNyYWl6LmljcGJyYXNpbC5nb3YuYnIvTENSYWNyYWl6djUuY3JsMB8GA1UdIwQY\\nMBaAFGmovnXZxO9s5xNF5GFu5Wj4tkBeMB0GA1UdDgQWBBRQOH1C5FHJB0NYY8Br\\nY6z\\/oHPz4DAPBgNVHRMBAf8EBTADAQH\\/MA0GCSqGSIb3DQEBDQUAA4ICAQB9ZGip\\nb+KsqGoaeE5p8BeGFnbj8UXfEoXBP5K1ggj6MUuzj33HvZqvrJ06uVOpIFlUX60A\\nNxYsSexMDqSzgXYcb21YcxbRcD1fYdq5lqk759i9BeGK6SvfyNeKaEwpdhBQK24v\\nkT1nxP7MeyN8vLwldchNlM28GrRuUwwHTOohN973juPwAdnJUIAxPjvZzzfNs2Oq\\n4\\/ksQbFgObb6ltBRDvS20J4wBUbDSjkkSww2gQP08NFtQXB\\/1vpFwP6wdfwpmIQ5\\ntHpi0UBW1rJJZ9AqGhS3ciB1om1chG9iRa\\/QzBqCHHGN\\/0hlrdsZMxEKdosuvNPp\\nhaJwlNS+ffo79KWPp6KLerx1Nq6QIVLTyqvWIqmpRjjFTv7dxwoFr8ioq+81K\\/nR\\notMu5D0CeqqzUXlbCVtLDOIMUSOVQ61IBHT1NwoVeABOl2qEdJ+sAxDuxFzIyh9F\\nVhBE4vnHaDArb28yESBvhUQmoEGrTSp4Ee8ynu3VkXI9hxVsQGooZbf0CpE5RKWj\\n2TLW0dvxIyGc1yH5LLMy75ejIyoskN6rSkO8mCy6bBOqtW5RpU7eZG+257hZ8y5Z\\nL67VWX+eHyMudrUw10gBG4dy\\/sdg2r82QAL1iuqPd37ZHy4GNKurtYUPou6IZ18x\\nPPs\\/KWglKa+00PErdGlLgNIYv8Y6QBabf9G6LQ==\\n-----END CERTIFICATE-----\\n","-----BEGIN CERTIFICATE-----\\nMIIGoTCCBImgAwIBAgIBATANBgkqhkiG9w0BAQ0FADCBlzELMAkGA1UEBhMCQlIx\\nEzARBgNVBAoMCklDUC1CcmFzaWwxPTA7BgNVBAsMNEluc3RpdHV0byBOYWNpb25h\\nbCBkZSBUZWNub2xvZ2lhIGRhIEluZm9ybWFjYW8gLSBJVEkxNDAyBgNVBAMMK0F1\\ndG9yaWRhZGUgQ2VydGlmaWNhZG9yYSBSYWl6IEJyYXNpbGVpcmEgdjUwHhcNMTYw\\nMzAyMTMwMTM4WhcNMjkwMzAyMjM1OTM4WjCBlzELMAkGA1UEBhMCQlIxEzARBgNV\\nBAoMCklDUC1CcmFzaWwxPTA7BgNVBAsMNEluc3RpdHV0byBOYWNpb25hbCBkZSBU\\nZWNub2xvZ2lhIGRhIEluZm9ybWFjYW8gLSBJVEkxNDAyBgNVBAMMK0F1dG9yaWRh\\nZGUgQ2VydGlmaWNhZG9yYSBSYWl6IEJyYXNpbGVpcmEgdjUwggIiMA0GCSqGSIb3\\nDQEBAQUAA4ICDwAwggIKAoICAQD3LXgabUWsF+gUXw\\/6YODeF2XkqEyfk3VehdsI\\nx+3\\/ERgdjCS\\/ouxYR0Epi2hdoMUVJDNf3XQfjAWXJyCoTneHYAl2McMdvoqtLB2i\\nleQlJiis0fTtYTJayee9BAIdIrCor1Lc0vozXCpDtq5nTwhjIocaZtcuFsdrkl+n\\nbfYxl5m7vjTkTMS6j8ffjmFzbNPDlJuV3Vy7AzapPVJrMl6UHPXCHMYMzl0KxR\\/4\\n7S5XGgmLYkYt8bNCHA3fg07y+Gtvgu+SNhMPwWKIgwhYw+9vErOnavRhOimYo4M2\\nAwNpNK0OKLI7Im5V094jFp4Ty+mlmfQH00k8nkSUEN+1TGGkhv16c2hukbx9iCfb\\nmk7im2hGKjQA8eH64VPYoS2qdKbPbd3xDDHN2croYKpy2U2oQTVBSf9hC3o6fKo3\\nzp0U3dNiw7ZgWKS9UwP31Q0gwgB1orZgLuF+LIppHYwxcTG\\/AovNWa4sTPukMiX2\\nL+p7uIHExTZJJU4YoDacQh\\/mfbPIz3261He4YFmQ35sfw3eKHQSOLyiVfev\\/n0l\\/\\nr308PijEd+d+Hz5RmqIzS8jYXZIeJxym4mEjE1fKpeP56Ea52LlIJ8ZqsJ3xzHWu\\n3WkAVz4hMqrX6BPMGW2IxOuEUQyIaCBg1lI6QLiPMHvo2\\/J7gu4YfqRcH6i27W3H\\nyzamEQIDAQABo4H1MIHyME4GA1UdIARHMEUwQwYFYEwBAQAwOjA4BggrBgEFBQcC\\nARYsaHR0cDovL2FjcmFpei5pY3BicmFzaWwuZ292LmJyL0RQQ2FjcmFpei5wZGYw\\nPwYDVR0fBDgwNjA0oDKgMIYuaHR0cDovL2FjcmFpei5pY3BicmFzaWwuZ292LmJy\\nL0xDUmFjcmFpenY1LmNybDAfBgNVHSMEGDAWgBRpqL512cTvbOcTReRhbuVo+LZA\\nXjAdBgNVHQ4EFgQUaai+ddnE72znE0XkYW7laPi2QF4wDwYDVR0TAQH\\/BAUwAwEB\\n\\/zAOBgNVHQ8BAf8EBAMCAQYwDQYJKoZIhvcNAQENBQADggIBABRt2\\/JiWapef7o\\/\\nplhR4PxymlMIp\\/JeZ5F0BZ1XafmYpl5g6pRokFrIRMFXLyEhlgo51I05InyCc9Td\\n6UXjlsOASTc\\/LRavyjB\\/8NcQjlRYDh6xf7OdP05mFcT\\/0+6bYRtNgsnUbr10pfsK\\n\\/UzyUvQWbumGS57hCZrAZOyd9MzukiF\\/azAa6JfoZk2nDkEudKOY8tRyTpMmDzN5\\nfufPSC3v7tSJUqTqo5z7roN\\/FmckRzGAYyz5XulbOc5\\/UsAT\\/tk+KP\\/clbbqd\\/hh\\nevmmdJclLr9qWZZcOgzuFU2YsgProtVu0fFNXGr6KK9fu44pOHajmMsTXK3X7r\\/P\\nwh19kFRow5F3RQMUZC6Re0YLfXh+ypnUSCzA+uL4JPtHIGyvkbWiulkustpOKUSV\\nwBPzvA2sQUOvqdbAR7C8jcHYFJMuK2HZFji7pxcWWab\\/NKsFcJ3sluDjmhizpQax\\nbYTfAVXu3q8yd0su\\/BHHhBpteyHvYyyz0Eb9LUysR2cMtWvfPU6vnoPgYvOGO1Cz\\niyGEsgKULkCH4o2Vgl1gQuKWO4V68rFW8a\\/jvq28sbY+y\\/Ao0I5ohpnBcQOAawiF\\nbz6yJtObajYMuztDDP8oY656EuuJXBJhuKAJPI\\/7WDtgfV8ffOh\\/iQGQATVMtgDN\\n0gv8bn5NdUX8UMNX1sHhU3H1UpoW\\n-----END CERTIFICATE-----\\n","-----BEGIN CERTIFICATE-----\\nMIIHUjCCBTqgAwIBAgIKcGwrRiXa9i64QTANBgkqhkiG9w0BAQ0FADBwMQswCQYD\\nVQQGDAJCUjETMBEGA1UECgwKSUNQLUJyYXNpbDE0MDIGA1UECwwrQXV0b3JpZGFk\\nZSBDZXJ0aWZpY2Fkb3JhIFJhaXogQnJhc2lsZWlyYSB2NTEWMBQGA1UEAwwNQUMg\\nU3luZ3VsYXJJRDAeFw0yMjA0MTgxODM1MTRaFw0yOTAzMDEyMzU5NTlaMFsxCzAJ\\nBgNVBAYTAkJSMRYwFAYDVQQLDA1BQyBTeW5ndWxhcklEMRMwEQYDVQQKDApJQ1At\\nQnJhc2lsMR8wHQYDVQQDDBZBQyBTeW5ndWxhcklEIE11bHRpcGxhMIICIjANBgkq\\nhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAnfheqOqUO3wiQAuJxnAb+F0OAVxBMN+T\\nZEwyVvSbCni4Ln5XNhs\\/fz7jfB3mGDs1IptiXNcJiRDgOy7tzukwPVRgLbKlVy52\\nkq\\/tr83cbskJcS6FfsO6T22xfOEO8uZ1uJ+jTPYOyBFjjOXBx9XB4NbNcpCE8KL9\\n+WStpmLS77\\/IUqbkUsD3oA+jHyHmnHqNayTmKnr4z\\/OxiTxNNayEsK2yiO686vkn\\np+5dy6G3axwlmQkbsXKnVeyKN4IuKUBTDKrxSmDuHievofjT\\/YwJ\\/DiT19lUOWoQ\\nGKhUax7WnWCw6ufPcPn1NsskZIlCPwKoY8RR4zCuLO8pzb\\/hCNoUAf0TNjnS9gHP\\n1hVE0PDqPxB9OT7ejMtmPAWLIw64\\/2m9cy8lV8UXO1bX1tToMF+q0pA\\/LTwDytdw\\n1AoM\\/OcNqVZRLxa59RZsuLbLPLC0tzRe13CzXBgecbotmKNi2hAaWMEyfjmvcJFV\\nI9CUqlmNenxUIf9huy7nrNAROLC47AmrTmuFIvlAhRBb7mu4GrYfsUg\\/IuKhCXHp\\nBpT01aBdFHAz0BYgdJVd6PvzyD9wkE9Mtfb81AFq9eWgOnt6CyD1DZl5cCv6dpW\\/\\nULXM+ObExX4hvgrH8yZHZZOsIKpikYodILZSgRm1q9JefYsa8nRlg\\/WGgIsgMevX\\nuhzwLf\\/5XU8CAwEAAaOCAgEwggH9MA4GA1UdDwEB\\/wQEAwIBBjAPBgNVHRMBAf8E\\nBTADAQH\\/MB8GA1UdIwQYMBaAFFA4fULkUckHQ1hjwGtjrP+gc\\/PgMB0GA1UdDgQW\\nBBST4f9+HeX15E3hOWKLIWmV5q9yFjCB2AYDVR0gBIHQMIHNMGUGB2BMAQIBgQUw\\nWjBYBggrBgEFBQcCARZMaHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3JlcG9zaXRv\\ncmlvL2FjLXN5bmd1bGFyaWQvZHBjL2RwYy1hYy1zeW5ndWxhcklELnBkZjBkBgZg\\nTAECA30wWjBYBggrBgEFBQcCARZMaHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3Jl\\ncG9zaXRvcmlvL2FjLXN5bmd1bGFyaWQvZHBjL2RwYy1hYy1zeW5ndWxhcklELnBk\\nZjCBvgYDVR0fBIG2MIGzMFKgUKBOhkxodHRwOi8vc3luZ3VsYXJpZC5jb20uYnIv\\ncmVwb3NpdG9yaW8vYWMtc3luZ3VsYXJpZC9sY3IvbGNyLWFjLXN5bmd1bGFyaWQu\\nY3JsMF2gW6BZhldodHRwOi8vaWNwLWJyYXNpbC5zeW5ndWxhcmlkLmNvbS5ici9y\\nZXBvc2l0b3Jpby9hYy1zeW5ndWxhcmlkL2xjci9sY3ItYWMtc3luZ3VsYXJpZC5j\\ncmwwDQYJKoZIhvcNAQENBQADggIBAJbsTc4B20N0qJj6bCSYsNy1E0WN9Bqmqs8u\\nrDBy7if6LNnxRbPNDkFZbE5SI\\/JxA4\\/XYx2tMzjdxdIZGGTTJFoP0V2yNZNXT9s5\\nAb\\/ksFFXex8eSandd1EraXzoUHDmrVdF\\/LTUSqNZdzvZvPglCHkTXoxMJJycMvay\\nOT6asVy9UWqCiVJvZFA8oOXvLSwR8Dt6M2NcBK9NpDaaqgjGKZlHeK2hDMNgRUaV\\nWK9QuWUwlJUMqK8U8Qi51iOJkM9jpv1Fg460TZqHU7BwLU1YoI7ADa2soVHYNcZa\\naWKO6L+74d6j3TueQ8jcnHw8moXV4zYSsMQau+yA5IlRlDYXQl4iCcG2wBbEAMNu\\nJUCmgg2G+jihAQfXWR\\/JRDCBaNPrFqVJPkZqGKqN60gCav6cxbYKH2ZSipY9nO7W\\n3sStJjIp5dUk55LVAdGMPc9IYDiYMR57TKZm+QX\\/zT6bliA4Lr0EnYeOP\\/Qvl2iR\\nSrL6dSAgyqxpZa2hH75ww+zWsO5qAbnCYwIkTvidixXOap5VBJYAG1d+o+IQ9+hQ\\ndtKCq46rQCeOV0L87lNCdC7iadLRxlYfePohfYY0avR2im1lxlTPPLUVfYPF3tGY\\nEEcQoU6JSdCnI9SBz0UOHNgKUc+rK9V034eCqiSCUUY+l8ifaEAdtMZnYpx5k+TL\\nCAwHh668\\n-----END CERTIFICATE-----\\n"]	2.16.76.1.2.1.133	t	f	2026-05-04 17:25:33	2026-04-30 21:01:38	2026-05-04 17:25:33
\.


--
-- Data for Name: ged_fluxo_etapas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_fluxo_etapas (id, instancia_id, nome, tipo, ordem, responsavel_id, status, prazo, comentario, concluido_em, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: ged_fluxo_instancias; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_fluxo_instancias (id, fluxo_id, documento_id, status, etapa_atual, iniciado_por, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: ged_fluxos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_fluxos (id, nome, descricao, definicao, ativo, criado_por, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: ged_pastas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_pastas (id, nome, descricao, parent_id, path, criado_por, created_at, updated_at, ativo, ug_id) FROM stdin;
4	GAB	Gabinete do Prefeito	\N	GAB	94	2026-05-01 19:05:19	2026-05-01 20:25:57	t	16
1	SME	Secretaria Municipal de Educação	\N	SME	94	2026-05-01 19:04:45	2026-05-01 20:26:17	t	16
2	SMG	Secretaria Municipal de Governo	\N	SMG	94	2026-05-01 19:04:57	2026-05-01 20:26:58	t	16
3	SMS	Secretaria Municipal de Saúde	\N	SMS	94	2026-05-01 19:05:08	2026-05-01 20:27:21	t	16
5	Relatorios Mensais	Relatórios do Fechamento Mensal	\N	Relatorios Mensais	94	2026-05-03 20:14:57	2026-05-03 20:14:57	t	16
\.


--
-- Data for Name: ged_permissions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_permissions (id, nome, descricao, created_at, updated_at) FROM stdin;
1	documento.visualizar	Visualizar documentos	2026-04-01 18:38:24	2026-04-01 18:38:24
2	documento.criar	Criar/fazer upload de documentos	2026-04-01 18:38:24	2026-04-01 18:38:24
3	documento.editar	Editar metadados de documentos	2026-04-01 18:38:24	2026-04-01 18:38:24
4	documento.excluir	Excluir documentos	2026-04-01 18:38:24	2026-04-01 18:38:24
5	documento.download	Fazer download de documentos	2026-04-01 18:38:24	2026-04-01 18:38:24
6	pasta.visualizar	Visualizar pastas e repositorio	2026-04-01 18:38:24	2026-04-01 18:38:24
7	pasta.criar	Criar pastas	2026-04-01 18:38:24	2026-04-01 18:38:24
8	pasta.editar	Renomear e mover pastas	2026-04-01 18:38:24	2026-04-01 18:38:24
9	pasta.excluir	Excluir pastas	2026-04-01 18:38:24	2026-04-01 18:38:24
10	fluxo.visualizar	Visualizar fluxos de trabalho	2026-04-01 18:38:24	2026-04-01 18:38:24
11	fluxo.criar	Criar fluxos de trabalho	2026-04-01 18:38:24	2026-04-01 18:38:24
12	fluxo.editar	Editar fluxos de trabalho	2026-04-01 18:38:24	2026-04-01 18:38:24
13	fluxo.gerenciar	Gerenciar instancias de fluxo	2026-04-01 18:38:24	2026-04-01 18:38:24
14	admin.usuarios	Gerenciar usuarios	2026-04-01 18:38:24	2026-04-01 18:38:24
15	admin.roles	Gerenciar perfis e permissoes	2026-04-01 18:38:24	2026-04-01 18:38:24
16	admin.configuracoes	Configuracoes do sistema	2026-04-01 18:38:24	2026-04-01 18:38:24
\.


--
-- Data for Name: ged_role_permissions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_role_permissions (role_id, permission_id) FROM stdin;
1	1
1	2
1	3
1	4
1	5
1	6
1	7
1	8
1	9
1	10
1	11
1	12
1	13
1	14
1	15
1	16
2	1
2	2
2	3
2	4
2	5
2	6
2	7
2	8
2	9
2	10
2	11
2	12
2	13
3	1
3	2
3	3
3	5
3	6
3	10
4	1
4	5
4	6
\.


--
-- Data for Name: ged_roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_roles (id, nome, descricao, created_at, updated_at) FROM stdin;
1	Administrador	Acesso total ao sistema GED	2026-04-01 18:38:24	2026-04-01 18:38:24
2	Gestor Documental	Gerencia documentos, pastas e fluxos	2026-04-01 18:38:24	2026-04-01 18:38:24
3	Editor	Pode criar e editar documentos	2026-04-01 18:38:24	2026-04-01 18:38:24
4	Visualizador	Apenas visualiza e faz download	2026-04-01 18:38:24	2026-04-01 18:38:24
\.


--
-- Data for Name: ged_sistemas_integrados; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_sistemas_integrados (id, codigo, nome, descricao, api_token_hash, api_token_prefix, ativo, ultimo_uso_em, created_at, updated_at, webhook_secret, eventos_assinatura) FROM stdin;
1	gpe	GPE - Sistema de Gestao Publica	Sistema legado que envia empenhos, liquidacoes e pagamentos	6c6e3789b2eb543233b313632dbf383d857dba538eb08dfeb0c444d934f03014	WbpDPlJG	t	2026-05-03 20:50:42	2026-05-02 15:09:48	2026-05-03 20:50:42	BBoliocwLkDrS4C5p6fqgmUOAxJSAFgnfTMfmPWJriJt7dtx	\N
\.


--
-- Data for Name: ged_tags; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_tags (id, nome, cor, created_at, updated_at) FROM stdin;
1	Urgente	#EF4444	2026-04-01 18:38:24	2026-04-01 18:38:24
2	Confidencial	#F59E0B	2026-04-01 18:38:24	2026-04-01 18:38:24
3	Revisao Pendente	#3B82F6	2026-04-01 18:38:24	2026-04-01 18:38:24
4	Aprovado	#22C55E	2026-04-01 18:38:24	2026-04-01 18:38:24
5	Arquivo Permanente	#6B7280	2026-04-01 18:38:24	2026-04-01 18:38:24
6	Em Tramitacao	#8B5CF6	2026-04-01 18:38:24	2026-04-01 18:38:24
\.


--
-- Data for Name: ged_tipos_documentais; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_tipos_documentais (id, nome, descricao, schema_metadados, ativo, created_at, updated_at, sistema_origem) FROM stdin;
1	Oficio	Comunicacao oficial entre orgaos	[{"tipo": "text", "campo": "numero", "label": "Numero do Oficio", "obrigatorio": true}, {"tipo": "text", "campo": "destinatario", "label": "Destinatario"}, {"tipo": "date", "campo": "data_emissao", "label": "Data de Emissao"}]	t	2026-04-01 18:38:24	2026-04-01 18:38:24	\N
2	Memorando	Comunicacao interna	[{"tipo": "text", "campo": "numero", "label": "Numero"}, {"tipo": "text", "campo": "setor_origem", "label": "Setor de Origem"}, {"tipo": "text", "campo": "setor_destino", "label": "Setor de Destino"}]	t	2026-04-01 18:38:24	2026-04-01 18:38:24	\N
3	Contrato	Contratos e termos aditivos	[{"tipo": "text", "campo": "numero_contrato", "label": "Numero do Contrato", "obrigatorio": true}, {"tipo": "text", "campo": "contratado", "label": "Contratado"}, {"tipo": "number", "campo": "valor", "label": "Valor (R$)"}, {"tipo": "date", "campo": "vigencia_inicio", "label": "Inicio da Vigencia"}, {"tipo": "date", "campo": "vigencia_fim", "label": "Fim da Vigencia"}]	t	2026-04-01 18:38:24	2026-04-01 18:38:24	\N
4	Nota Fiscal	Notas fiscais de servicos e produtos	[{"tipo": "text", "campo": "numero_nf", "label": "Numero da NF", "obrigatorio": true}, {"tipo": "text", "campo": "fornecedor", "label": "Fornecedor"}, {"tipo": "number", "campo": "valor", "label": "Valor (R$)"}, {"tipo": "date", "campo": "data_emissao", "label": "Data de Emissao"}]	t	2026-04-01 18:38:24	2026-04-01 18:38:24	\N
7	Decreto	Decretos municipais	[{"tipo": "text", "campo": "numero", "label": "Numero do Decreto", "obrigatorio": true}, {"tipo": "date", "campo": "data_publicacao", "label": "Data de Publicacao"}]	t	2026-04-01 18:38:24	2026-04-01 18:38:24	\N
8	Lei	Leis municipais	[{"tipo": "text", "campo": "numero", "label": "Numero da Lei", "obrigatorio": true}, {"tipo": "date", "campo": "data_publicacao", "label": "Data de Publicacao"}]	t	2026-04-01 18:38:24	2026-04-01 18:38:24	\N
10	Certidao	Certidoes diversas	\N	t	2026-04-01 18:38:24	2026-04-01 18:38:24	\N
11	Relatorio	Relatorios tecnicos e gerenciais	\N	t	2026-04-01 18:38:24	2026-04-01 18:38:24	\N
12	Outros	Documentos diversos	\N	t	2026-04-01 18:38:24	2026-04-01 18:38:24	\N
9	Alvara	Alvaras e licencas	[{"tipo": "number", "campo": "numero", "label": "Número", "opcoes": null, "obrigatorio": true}, {"tipo": "date", "campo": "data", "label": "Data", "opcoes": null, "obrigatorio": true}, {"tipo": "number", "campo": "documento", "label": "Documento", "opcoes": "CPF", "obrigatorio": true}, {"tipo": "text", "campo": "nome", "label": "Nome", "opcoes": null, "obrigatorio": true}]	t	2026-04-01 18:38:24	2026-04-01 19:28:03	\N
17	Ata	Atas de reuniao e sessao	[{"tipo": "date", "campo": "data_reuniao", "label": "Data da Reuniao"}, {"tipo": "text", "campo": "local", "label": "Local"}, {"tipo": "text", "campo": "participantes", "label": "Participantes"}]	t	2026-04-30 12:08:08	2026-04-30 12:08:08	\N
18	Portaria	Portarias administrativas	[{"tipo": "text", "campo": "numero", "label": "Numero da Portaria", "obrigatorio": true}, {"tipo": "date", "campo": "data_publicacao", "label": "Data de Publicacao"}]	t	2026-04-30 12:08:08	2026-04-30 12:08:08	\N
25	Decisao Administrativa	Decisao formal de processo (deferido/indeferido/parcial)	[]	t	2026-05-01 18:58:51	2026-05-01 18:58:51	\N
26	Solicitaçoes	Solicitaçoes Diveras	[{"tipo": "number", "campo": "n_processo", "label": "Nº Processo", "opcoes": null, "obrigatorio": true}, {"tipo": "date", "campo": "data", "label": "Data", "opcoes": null, "obrigatorio": true}]	t	2026-05-01 19:09:46	2026-05-01 19:09:46	\N
27	Empenhos Orçamentarios	Empenhos Orçamentarios	[]	t	2026-05-02 20:11:40	2026-05-02 20:44:08	\N
29	Reforço de Empenho	Reforço de Empenho	[]	t	2026-05-03 13:55:19	2026-05-03 13:55:19	\N
30	Anulação de Empenho	Anulação de Empenho	[]	t	2026-05-03 13:56:03	2026-05-03 13:56:03	\N
28	Liquidação de Empenho	Liquidação de Empenho	[]	t	2026-05-03 13:14:22	2026-05-03 13:56:49	\N
31	Pagamento de Empenho	Pagamento de Empenho	[]	t	2026-05-03 13:58:01	2026-05-03 13:58:01	\N
32	Anulação de Pagamento	Anulação de Pagamento	[]	t	2026-05-03 13:58:48	2026-05-03 13:58:48	\N
33	Balancete da Despesa	Balancete da Despesa	[]	t	2026-05-03 20:13:01	2026-05-03 20:13:01	\N
\.


--
-- Data for Name: ged_user_roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_user_roles (user_id, role_id) FROM stdin;
189	1
94	1
94	2
94	3
94	4
\.


--
-- Data for Name: ged_webhook_logs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_webhook_logs (id, sistema_origem, documento_id, evento, callback_url, payload, signature_header, sucesso, http_status, response_body, erro, tentativas, duracao_ms, enviado_em, created_at, updated_at) FROM stdin;
1	gpe	26	assinatura.individual	http://gpe.local/webhooks/gpedocs/receber	{"evento":"assinatura.individual","sistema_origem":"gpe","numero_externo":"1\\/2026\\/1","documento_id":26,"enviado_em":"2026-05-02T21:00:14+00:00","visualizacao_url":"http:\\/\\/localhost:8000\\/documentos\\/26","pdf_assinado_url":"http:\\/\\/localhost:8000\\/documentos\\/26\\/download","tipo_assinatura":"qualificada","signatario_id":94,"cpf":"85183865604","assinado_em":"2026-05-02T21:00:14+00:00","todas_assinadas":false}	sha256=46f6b677c45451589851445d5bae6aeef42f4537ca9f36428520e4442c66d284	t	200	<!DOCTYPE html>\r\n<html lang="en">\r\n<head>\r\n    <meta charset="utf-8">\r\n    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">\r\n    <meta http-equiv="x-ua-compatible" content="ie=edge">\r\n    <meta name="author" content="GPE Cloud - Login">\r\n    <title>GPE Cloud - Login</title>\r\n    <link rel="shortcut icon" href="../favicon.ico">\r\n\r\n    <link rel="stylesheet" href="http://gpe.local/layout/assets/css/main.min.css">\r\n    <link rel="stylesheet" href="http://gpe.local/layout/assets/css/novo.css">\r\n    <link href="http://gpe.local/bower_components/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css">\r\n\r\n    <style>\r\n        @media (width >= 1080px) {\r\n            #news-tab {\r\n                padding: 0 20vh 0 20vh;\r\n            }\r\n\r\n            .news-item {\r\n                width: 100vh;\r\n            }\r\n        }\r\n\r\n        @media (width < 1080px) {\r\n            p, span {\r\n                font-size: 0.8rem !important;\r\n            }\r\n        }\r\n\r\n        .left-column {\r\n            /* background-color: #5ab0c5; */\r\n            height: 100vh;\r\n            text-align: center;\r\n\r\n            background: rgb(2,0,36);\r\n            background: linear-gradient(90deg, rgba(2,0,36,1) 0%, rgba(90,176,197,1) 0%, rgba(231,219,206,1) 100%, rgba(156,156,163,1) 100%, rgba(0,212,255,1) 100%);\r\n        }\r\n\r\n        /* .card-img-top {\r\n            padding: 2rem 2rem 0 2rem;\r\n        } */\r\n\r\n        .login-form {\r\n            margin-top: 2vh;\r\n            display: block;\r\n        }\r\n\r\n        .login-left {\r\n            position: sticky;\r\n            position: -webkit-sticky;\r\n            top: 0;\r\n            display: block;\r\n        }\r\n\r\n        .footer-icons a {\r\n            color: white;\r\n            font-size: 1.5rem;\r\n            padding: 10px;\r\n            text-shadow: 12px 12px 12px rgba(0, 0, 0, 0.1), 26px 6px 20px gray;\r\n        }\r\n\r\n        .footer-icons i:hover {\r\n            transition: all 200ms cubic-bezier(0.175, 0.82, 0.9	\N	1	3513	2026-05-02 21:00:14	2026-05-02 21:00:14	2026-05-02 21:00:18
2	gpe	26	assinatura.individual	http://gpe.local/webhooks/gpedocs/receber	{"evento":"assinatura.individual","sistema_origem":"gpe","numero_externo":"1\\/2026\\/1","documento_id":26,"enviado_em":"2026-05-02T21:15:19+00:00","visualizacao_url":"http:\\/\\/localhost:8000\\/documentos\\/26","pdf_assinado_url":"http:\\/\\/localhost:8000\\/documentos\\/26\\/download","tipo_assinatura":"simples","signatario_id":191,"cpf":null,"assinado_em":"2026-05-02T21:15:19+00:00","todas_assinadas":false,"simulado":true}	sha256=077acf4e43411e6bfb4ee9849669ee06bab27193db7489b492c7cc3ec16f47f6	t	200	<!DOCTYPE html>\r\n<html lang="en">\r\n<head>\r\n    <meta charset="utf-8">\r\n    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">\r\n    <meta http-equiv="x-ua-compatible" content="ie=edge">\r\n    <meta name="author" content="GPE Cloud - Login">\r\n    <title>GPE Cloud - Login</title>\r\n    <link rel="shortcut icon" href="../favicon.ico">\r\n\r\n    <link rel="stylesheet" href="http://gpe.local/layout/assets/css/main.min.css">\r\n    <link rel="stylesheet" href="http://gpe.local/layout/assets/css/novo.css">\r\n    <link href="http://gpe.local/bower_components/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css">\r\n\r\n    <style>\r\n        @media (width >= 1080px) {\r\n            #news-tab {\r\n                padding: 0 20vh 0 20vh;\r\n            }\r\n\r\n            .news-item {\r\n                width: 100vh;\r\n            }\r\n        }\r\n\r\n        @media (width < 1080px) {\r\n            p, span {\r\n                font-size: 0.8rem !important;\r\n            }\r\n        }\r\n\r\n        .left-column {\r\n            /* background-color: #5ab0c5; */\r\n            height: 100vh;\r\n            text-align: center;\r\n\r\n            background: rgb(2,0,36);\r\n            background: linear-gradient(90deg, rgba(2,0,36,1) 0%, rgba(90,176,197,1) 0%, rgba(231,219,206,1) 100%, rgba(156,156,163,1) 100%, rgba(0,212,255,1) 100%);\r\n        }\r\n\r\n        /* .card-img-top {\r\n            padding: 2rem 2rem 0 2rem;\r\n        } */\r\n\r\n        .login-form {\r\n            margin-top: 2vh;\r\n            display: block;\r\n        }\r\n\r\n        .login-left {\r\n            position: sticky;\r\n            position: -webkit-sticky;\r\n            top: 0;\r\n            display: block;\r\n        }\r\n\r\n        .footer-icons a {\r\n            color: white;\r\n            font-size: 1.5rem;\r\n            padding: 10px;\r\n            text-shadow: 12px 12px 12px rgba(0, 0, 0, 0.1), 26px 6px 20px gray;\r\n        }\r\n\r\n        .footer-icons i:hover {\r\n            transition: all 200ms cubic-bezier(0.175, 0.82, 0.9	\N	1	800	2026-05-02 21:15:19	2026-05-02 21:15:19	2026-05-02 21:15:20
3	gpe	26	assinatura.todas_concluidas	http://gpe.local/webhooks/gpedocs/receber	{"evento":"assinatura.todas_concluidas","sistema_origem":"gpe","numero_externo":"1\\/2026\\/1","documento_id":26,"enviado_em":"2026-05-02T21:15:20+00:00","visualizacao_url":"http:\\/\\/localhost:8000\\/documentos\\/26","pdf_assinado_url":"http:\\/\\/localhost:8000\\/documentos\\/26\\/download","concluido_em":"2026-05-02T21:15:20+00:00","simulado":true}	sha256=5634298e035f6cad88b4e3be8d263c6fec70249d5e2a09146085d5c3a3f4f9e4	t	200	<!DOCTYPE html>\r\n<html lang="en">\r\n<head>\r\n    <meta charset="utf-8">\r\n    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">\r\n    <meta http-equiv="x-ua-compatible" content="ie=edge">\r\n    <meta name="author" content="GPE Cloud - Login">\r\n    <title>GPE Cloud - Login</title>\r\n    <link rel="shortcut icon" href="../favicon.ico">\r\n\r\n    <link rel="stylesheet" href="http://gpe.local/layout/assets/css/main.min.css">\r\n    <link rel="stylesheet" href="http://gpe.local/layout/assets/css/novo.css">\r\n    <link href="http://gpe.local/bower_components/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css">\r\n\r\n    <style>\r\n        @media (width >= 1080px) {\r\n            #news-tab {\r\n                padding: 0 20vh 0 20vh;\r\n            }\r\n\r\n            .news-item {\r\n                width: 100vh;\r\n            }\r\n        }\r\n\r\n        @media (width < 1080px) {\r\n            p, span {\r\n                font-size: 0.8rem !important;\r\n            }\r\n        }\r\n\r\n        .left-column {\r\n            /* background-color: #5ab0c5; */\r\n            height: 100vh;\r\n            text-align: center;\r\n\r\n            background: rgb(2,0,36);\r\n            background: linear-gradient(90deg, rgba(2,0,36,1) 0%, rgba(90,176,197,1) 0%, rgba(231,219,206,1) 100%, rgba(156,156,163,1) 100%, rgba(0,212,255,1) 100%);\r\n        }\r\n\r\n        /* .card-img-top {\r\n            padding: 2rem 2rem 0 2rem;\r\n        } */\r\n\r\n        .login-form {\r\n            margin-top: 2vh;\r\n            display: block;\r\n        }\r\n\r\n        .login-left {\r\n            position: sticky;\r\n            position: -webkit-sticky;\r\n            top: 0;\r\n            display: block;\r\n        }\r\n\r\n        .footer-icons a {\r\n            color: white;\r\n            font-size: 1.5rem;\r\n            padding: 10px;\r\n            text-shadow: 12px 12px 12px rgba(0, 0, 0, 0.1), 26px 6px 20px gray;\r\n        }\r\n\r\n        .footer-icons i:hover {\r\n            transition: all 200ms cubic-bezier(0.175, 0.82, 0.9	\N	1	668	2026-05-02 21:15:20	2026-05-02 21:15:20	2026-05-02 21:15:20
4	gpe	26	assinatura.todas_concluidas	http://gpe.local/webhooks/gpedocs/receber	{"evento":"assinatura.todas_concluidas","sistema_origem":"gpe","numero_externo":"1\\/2026\\/1","documento_id":26,"enviado_em":"2026-05-02T21:24:23+00:00","visualizacao_url":"http:\\/\\/localhost:8000\\/documentos\\/26","pdf_assinado_url":"http:\\/\\/localhost:8000\\/documentos\\/26\\/download","concluido_em":"2026-05-02T21:24:23+00:00","reenvio":true}	sha256=057eb2764034815c6bab0c6d4210feb8902211d73c590be90d4cc4f353e7bf8e	t	200	{"success":true,"message":"OK"}	\N	1	597	2026-05-02 21:24:23	2026-05-02 21:24:23	2026-05-02 21:24:23
5	gpe	28	assinatura.individual	http://gpe.local/webhooks/gpedocs/receber	{"evento":"assinatura.individual","sistema_origem":"gpe","numero_externo":"1\\/2026\\/482","documento_id":28,"enviado_em":"2026-05-03T14:03:20+00:00","visualizacao_url":"http:\\/\\/localhost:8000\\/documentos\\/28","pdf_assinado_url":"http:\\/\\/localhost:8000\\/documentos\\/28\\/download","tipo_assinatura":"qualificada","signatario_id":94,"cpf":"85183865604","assinado_em":"2026-05-03T14:03:20+00:00","todas_assinadas":true}	sha256=f97000931cbe14e8d8929b777e872609fd11e62e34f8bc900d248b748434bdfd	t	200	{"success":true,"message":"OK"}	\N	1	2647	2026-05-03 14:03:20	2026-05-03 14:03:20	2026-05-03 14:03:22
6	gpe	28	assinatura.todas_concluidas	http://gpe.local/webhooks/gpedocs/receber	{"evento":"assinatura.todas_concluidas","sistema_origem":"gpe","numero_externo":"1\\/2026\\/482","documento_id":28,"enviado_em":"2026-05-03T14:03:22+00:00","visualizacao_url":"http:\\/\\/localhost:8000\\/documentos\\/28","pdf_assinado_url":"http:\\/\\/localhost:8000\\/documentos\\/28\\/download","concluido_em":"2026-05-03T14:03:22+00:00"}	sha256=63595ac1bc5fbe350a50eacf55aef5b8da462e9fdb8896d142e014e7767633a6	t	200	{"success":true,"message":"OK"}	\N	1	384	2026-05-03 14:03:22	2026-05-03 14:03:22	2026-05-03 14:03:23
7	gpe	27	assinatura.individual	http://gpe.local/webhooks/gpedocs/receber	{"evento":"assinatura.individual","sistema_origem":"gpe","numero_externo":"1\\/2026\\/481","documento_id":27,"enviado_em":"2026-05-03T14:03:48+00:00","visualizacao_url":"http:\\/\\/localhost:8000\\/documentos\\/27","pdf_assinado_url":"http:\\/\\/localhost:8000\\/documentos\\/27\\/download","tipo_assinatura":"qualificada","signatario_id":94,"cpf":"85183865604","assinado_em":"2026-05-03T14:03:48+00:00","todas_assinadas":true}	sha256=18c7d92b03a828b26a27480b10583cd1a75e66f20dc7fea73c6662c4944a76b0	t	200	{"success":true,"message":"OK"}	\N	1	488	2026-05-03 14:03:48	2026-05-03 14:03:48	2026-05-03 14:03:49
8	gpe	27	assinatura.todas_concluidas	http://gpe.local/webhooks/gpedocs/receber	{"evento":"assinatura.todas_concluidas","sistema_origem":"gpe","numero_externo":"1\\/2026\\/481","documento_id":27,"enviado_em":"2026-05-03T14:03:49+00:00","visualizacao_url":"http:\\/\\/localhost:8000\\/documentos\\/27","pdf_assinado_url":"http:\\/\\/localhost:8000\\/documentos\\/27\\/download","concluido_em":"2026-05-03T14:03:49+00:00"}	sha256=48fc29b0bbc6bce5c8249ae32e53cefd2c50f1e6455dedaab1fb64e5db76f433	t	200	{"success":true,"message":"OK"}	\N	1	333	2026-05-03 14:03:49	2026-05-03 14:03:49	2026-05-03 14:03:49
9	gpe	34	assinatura.individual	http://gpe.local/webhooks/gpedocs/receber	{"evento":"assinatura.individual","sistema_origem":"gpe","numero_externo":"1\\/2026\\/7612","documento_id":34,"enviado_em":"2026-05-03T14:05:57+00:00","visualizacao_url":"http:\\/\\/localhost:8000\\/documentos\\/34","pdf_assinado_url":"http:\\/\\/localhost:8000\\/documentos\\/34\\/download","tipo_assinatura":"qualificada","signatario_id":94,"cpf":"85183865604","assinado_em":"2026-05-03T14:05:57+00:00","todas_assinadas":true}	sha256=90f1c74a3034a07cf2787c229e0b8e3c6b9f64d3f1a0674b7b7467acb1753957	t	200	{"success":true,"message":"OK"}	\N	1	443	2026-05-03 14:05:57	2026-05-03 14:05:57	2026-05-03 14:05:57
10	gpe	34	assinatura.todas_concluidas	http://gpe.local/webhooks/gpedocs/receber	{"evento":"assinatura.todas_concluidas","sistema_origem":"gpe","numero_externo":"1\\/2026\\/7612","documento_id":34,"enviado_em":"2026-05-03T14:05:57+00:00","visualizacao_url":"http:\\/\\/localhost:8000\\/documentos\\/34","pdf_assinado_url":"http:\\/\\/localhost:8000\\/documentos\\/34\\/download","concluido_em":"2026-05-03T14:05:57+00:00"}	sha256=ea47ce3ec3082f4593980ead5e281b769cea7fa5517a77b81c2c364e1d4e0407	t	200	{"success":true,"message":"OK"}	\N	1	310	2026-05-03 14:05:57	2026-05-03 14:05:57	2026-05-03 14:05:58
11	gpe	33	assinatura.individual	http://gpe.local/webhooks/gpedocs/receber	{"evento":"assinatura.individual","sistema_origem":"gpe","numero_externo":"1\\/2026\\/7611","documento_id":33,"enviado_em":"2026-05-03T14:06:12+00:00","visualizacao_url":"http:\\/\\/localhost:8000\\/documentos\\/33","pdf_assinado_url":"http:\\/\\/localhost:8000\\/documentos\\/33\\/download","tipo_assinatura":"simples","signatario_id":94,"cpf":"85183865604","assinado_em":"2026-05-03T14:06:12+00:00","todas_assinadas":true}	sha256=fd5ce8216beba7ceb99ed809ee11a32955eb6916374765039c18ee0d95fb9aff	t	200	{"success":true,"message":"OK"}	\N	1	377	2026-05-03 14:06:12	2026-05-03 14:06:12	2026-05-03 14:06:12
12	gpe	33	assinatura.todas_concluidas	http://gpe.local/webhooks/gpedocs/receber	{"evento":"assinatura.todas_concluidas","sistema_origem":"gpe","numero_externo":"1\\/2026\\/7611","documento_id":33,"enviado_em":"2026-05-03T14:06:12+00:00","visualizacao_url":"http:\\/\\/localhost:8000\\/documentos\\/33","pdf_assinado_url":"http:\\/\\/localhost:8000\\/documentos\\/33\\/download","concluido_em":"2026-05-03T14:06:12+00:00"}	sha256=4323440245ae2f421aa5dbb59365c009e0cc7ecb7f9becba8793aee844184b09	t	200	{"success":true,"message":"OK"}	\N	1	351	2026-05-03 14:06:12	2026-05-03 14:06:12	2026-05-03 14:06:12
13	gpe	32	assinatura.recusada	http://gpe.local/webhooks/gpedocs/receber	{"evento":"assinatura.recusada","sistema_origem":"gpe","numero_externo":"1\\/2026\\/7610","documento_id":32,"enviado_em":"2026-05-03T14:14:31+00:00","visualizacao_url":"http:\\/\\/localhost:8000\\/documentos\\/32","pdf_assinado_url":"http:\\/\\/localhost:8000\\/documentos\\/32\\/download","signatario_id":94,"cpf":null,"motivo":"esta errado o historico","recusado_em":"2026-05-03T14:14:31+00:00"}	sha256=1f2a1d88e2be7f81f47dcd15261bfd8f4c164e86df077f5e934f45f2bd5d1ea7	t	200	{"success":true,"message":"OK"}	\N	1	487	2026-05-03 14:14:31	2026-05-03 14:14:31	2026-05-03 14:14:32
14	gpe	38	assinatura.individual	http://gpe.local/webhooks/gpedocs/receber	{"evento":"assinatura.individual","sistema_origem":"gpe","numero_externo":"1\\/2026\\/2178","documento_id":38,"enviado_em":"2026-05-03T14:23:23+00:00","visualizacao_url":"http:\\/\\/localhost:8000\\/documentos\\/38","pdf_assinado_url":"http:\\/\\/localhost:8000\\/documentos\\/38\\/download","tipo_assinatura":"qualificada","signatario_id":94,"cpf":"85183865604","assinado_em":"2026-05-03T14:23:23+00:00","todas_assinadas":false}	sha256=3732b8076a0b59e3321ec64e23f65f27da0eea3db47a7e470674032f20b7c82a	t	200	{"success":true,"message":"OK"}	\N	1	642	2026-05-03 14:23:23	2026-05-03 14:23:23	2026-05-03 14:23:23
15	gpe	38	assinatura.recusada	http://gpe.local/webhooks/gpedocs/receber	{"evento":"assinatura.recusada","sistema_origem":"gpe","numero_externo":"1\\/2026\\/2178","documento_id":38,"enviado_em":"2026-05-03T14:23:49+00:00","visualizacao_url":"http:\\/\\/localhost:8000\\/documentos\\/38","pdf_assinado_url":"http:\\/\\/localhost:8000\\/documentos\\/38\\/download","signatario_id":94,"cpf":null,"motivo":"O historico ta muito mal feito","recusado_em":"2026-05-03T14:23:49+00:00"}	sha256=21e3ba35626851fddef9015267d99c8b37e57284a87988f0259c4cac1b82fc02	t	200	{"success":true,"message":"OK"}	\N	1	551	2026-05-03 14:23:49	2026-05-03 14:23:49	2026-05-03 14:23:49
16	gpe	42	assinatura.recusada	http://gpe.local/webhooks/gpedocs/receber	{"evento":"assinatura.recusada","sistema_origem":"gpe","numero_externo":"1\\/2026\\/231208","documento_id":42,"enviado_em":"2026-05-03T14:27:39+00:00","visualizacao_url":"http:\\/\\/localhost:8000\\/documentos\\/42","pdf_assinado_url":"http:\\/\\/localhost:8000\\/documentos\\/42\\/download","signatario_id":94,"cpf":null,"motivo":"Pagamento Foi feito com recurso errado","recusado_em":"2026-05-03T14:27:39+00:00"}	sha256=d7496029e01433e41f821fb3bf7dc01b1bc8c12315dc0ede53ab92778c3542e6	t	200	{"success":true,"message":"OK"}	\N	1	708	2026-05-03 14:27:39	2026-05-03 14:27:39	2026-05-03 14:27:39
17	gpe	41	assinatura.individual	http://gpe.local/webhooks/gpedocs/receber	{"evento":"assinatura.individual","sistema_origem":"gpe","numero_externo":"1\\/2026\\/231209","documento_id":41,"enviado_em":"2026-05-03T14:28:01+00:00","visualizacao_url":"http:\\/\\/localhost:8000\\/documentos\\/41","pdf_assinado_url":"http:\\/\\/localhost:8000\\/documentos\\/41\\/download","tipo_assinatura":"qualificada","signatario_id":94,"cpf":"85183865604","assinado_em":"2026-05-03T14:28:01+00:00","todas_assinadas":true}	sha256=718669838c9da45b7185b1ab874dbdc88f5b934debe7737ea0bd10b543b5b634	t	200	{"success":true,"message":"OK"}	\N	1	632	2026-05-03 14:28:01	2026-05-03 14:28:01	2026-05-03 14:28:02
18	gpe	41	assinatura.todas_concluidas	http://gpe.local/webhooks/gpedocs/receber	{"evento":"assinatura.todas_concluidas","sistema_origem":"gpe","numero_externo":"1\\/2026\\/231209","documento_id":41,"enviado_em":"2026-05-03T14:28:02+00:00","visualizacao_url":"http:\\/\\/localhost:8000\\/documentos\\/41","pdf_assinado_url":"http:\\/\\/localhost:8000\\/documentos\\/41\\/download","concluido_em":"2026-05-03T14:28:02+00:00"}	sha256=6192655e7d584f5b8f3abdfdd71235dbe80d39c12d619ebeca0beb01bca65e96	t	200	{"success":true,"message":"OK"}	\N	1	552	2026-05-03 14:28:02	2026-05-03 14:28:02	2026-05-03 14:28:02
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_04_01_000001_create_ged_tables	1
5	2026_04_01_000002_add_ativo_to_ged_pastas	2
6	2026_04_01_000003_create_ged_favoritos_table	3
7	2026_04_06_000001_add_qr_code_token_to_ged_documentos	4
8	2026_04_06_000002_create_ged_assinaturas_tables	5
9	2026_04_07_000001_create_proc_tables	6
10	2026_04_07_000002_create_proc_memorandos_tables	7
11	2026_04_07_000003_create_proc_circulares_tables	8
12	2026_04_07_000004_create_proc_oficios_tables	9
13	2026_04_30_000001_create_ged_certificados_and_extend_assinaturas	10
14	2026_04_30_120000_create_ugs_and_organograma	11
15	2026_04_30_140000_add_enderecos_to_ugs_and_organograma	12
16	2026_04_30_150000_add_legado_id_for_import	13
17	2026_04_30_160000_add_campos_extras_ug_organograma	14
18	2026_05_01_000001_multi_tenant_por_ug	15
19	2026_05_01_120000_destinos_unificados_proc	16
20	2026_05_01_140000_add_acesso_geral_ug_users	17
21	2026_05_01_150000_proc_memorando_tramitacoes	18
22	2026_05_01_180000_integracao_externa	19
23	2026_05_01_190000_webhook_logs_e_eventos	20
24	2026_05_05_000001_create_portal_servicos_tables	21
25	2026_05_05_000002_add_portal_slug_to_ugs	22
26	2026_05_05_000003_create_portal_cidadaos_e_solicitacoes	23
27	2026_05_05_000004_add_setor_e_anonimo_portal	24
28	2026_05_05_000005_unique_codigo_solicitacao_por_ug	25
29	2026_05_05_000006_add_banner_portal_to_ugs	26
30	2026_05_05_000007_create_portal_banners	27
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: portal_banners; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.portal_banners (id, ug_id, imagem_path, titulo, subtitulo, link_url, link_label, ordem, ativo, created_at, updated_at) FROM stdin;
1	17	banners/ug-002-0da2b8.png	teste 1	teste 2	\N	\N	0	t	2026-05-04 18:53:18	2026-05-04 18:53:18
3	17	banners/ug-002-89266102.png	teste banner 2	teste teste teste teste teste teste	\N	\N	1	t	2026-05-04 19:01:47	2026-05-04 19:01:47
4	17	banners/ug-002-12c4a2cc.png	Imóvel 7904 (0106001600480001): Histórico do imóvel não encontrado para o exercício.	Imóvel 9349 (0201006609200001): Histórico do imóvel não encontrado para o exercício.	\N	\N	2	t	2026-05-04 19:02:33	2026-05-04 19:02:33
\.


--
-- Data for Name: portal_categorias_servicos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.portal_categorias_servicos (id, ug_id, nome, slug, icone, cor, descricao, ordem, ativo, created_at, updated_at) FROM stdin;
1	16	Saúde	saude	fas fa-heartbeat	red	Atendimento medico, exames, vacinacao e medicamentos	0	t	2026-05-04 13:48:58	2026-05-04 13:48:58
2	16	Educação	educacao	fas fa-graduation-cap	blue	Matriculas, transporte escolar, bolsas e creches	1	t	2026-05-04 13:48:58	2026-05-04 13:48:58
3	16	Tributos	tributos	fas fa-file-invoice-dollar	amber	IPTU, ISS, taxas, certidoes e parcelamentos	2	t	2026-05-04 13:48:58	2026-05-04 13:48:58
4	16	Cidadania	cidadania	fas fa-id-card	indigo	Documentos, registros e direitos do cidadao	3	t	2026-05-04 13:48:58	2026-05-04 13:48:58
5	16	Obras	obras	fas fa-hard-hat	orange	Alvaras, habite-se, regularizacao e fiscalizacao	4	t	2026-05-04 13:48:58	2026-05-04 13:48:58
6	16	Meio Ambiente	meio-ambiente	fas fa-leaf	green	Licenciamento ambiental, podas, denuncias e limpeza	5	t	2026-05-04 13:48:58	2026-05-04 13:48:58
7	16	Assistência Social	assistencia-social	fas fa-hands-helping	pink	CRAS, beneficios, programas sociais e cadastros	6	t	2026-05-04 13:48:58	2026-05-04 13:48:58
8	16	Transporte	transporte	fas fa-bus	cyan	Transporte publico, estacionamento e mobilidade urbana	7	t	2026-05-04 13:48:58	2026-05-04 13:48:58
9	16	Ouvidoria	ouvidoria	fas fa-folder	cyan	Solicitação de informações, denuncia, o cidadão exercendo sua voz	2	t	2026-05-04 14:16:17	2026-05-04 14:16:17
10	17	Ouvidoria	ouvidoria	fas fa-folder	blue	Canal de denuncias e Sugestoes do Cidadao	1	t	2026-05-04 16:49:46	2026-05-04 16:49:46
11	17	E-Sic	e-sic	fas fa-folder	blue	Sistema de Informações ao Cidadão	2	t	2026-05-04 17:01:41	2026-05-04 17:01:41
\.


--
-- Data for Name: portal_cidadaos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.portal_cidadaos (id, nome, email, cpf, telefone, senha, email_verificado_em, token_verificacao, ativo, remember_token, created_at, updated_at) FROM stdin;
1	Gabriel Jardim	joeljardim@gmail.com	14030742670	33998088771	$2y$12$mv/zkoZYAN4DaeJsUPPmR.czaF88djs0E26Zl0N/zule1NwXO0YPC	\N	\N	t	K1vvWMDbceOckLKKmjnHjFu3kmYaWOZQirOeiTr7dNooM7uv3zIVkRlDx75l	2026-05-04 14:22:36	2026-05-04 14:22:36
\.


--
-- Data for Name: portal_servicos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.portal_servicos (id, ug_id, categoria_id, titulo, slug, publico_alvo, descricao_curta, descricao_completa, requisitos, documentos_necessarios, prazo_entrega, custo, canais, orgao_responsavel, legislacao, palavras_chave, icone, publicado, visualizacoes, ordem, created_at, updated_at, permite_anonimo, setor_responsavel_id, tipo_processo_id) FROM stdin;
2	16	2	Matrícula em Creche Municipal	matricula-em-creche-municipal	cidadao	Solicitacao de vaga em creche para criancas de 0 a 3 anos.	Inscricao para vaga em creches municipais, com prioridade definida por criterios socioeconomicos e proximidade da residencia.	Crianca com idade entre 0 e 3 anos e 11 meses. Familia residente no municipio.	["Certidao de nascimento da crianca","Comprovante de residencia","CPF dos responsaveis","Comprovante de renda familiar"]	Conforme calendario letivo / lista de espera	Gratuito	{"online":true,"presencial":true}	Secretaria de Educacao	LDB 9.394/96 — Lei de Diretrizes e Bases da Educacao	["creche","matricula","crianca","educacao infantil","vaga"]	fas fa-baby	t	0	1	2026-05-04 13:48:59	2026-05-04 13:48:59	f	\N	\N
4	16	5	Alvará de Construção	alvara-de-construcao	cidadao	Autorizacao para construcao, reforma ou ampliacao de imovel.	Documento obrigatorio para inicio de obras de construcao, reforma ou ampliacao em imoveis localizados no municipio. Verifica conformidade com o Plano Diretor.	Imovel regularizado, projeto arquitetonico assinado por profissional habilitado.	["Projeto arquitetonico em PDF","ART\\/RRT do responsavel tecnico","Escritura ou contrato do imovel","IPTU em dia","Memorial descritivo"]	Ate 30 dias uteis	Conforme tabela de taxas — varia com o tamanho da obra	{"online":true,"presencial":true}	Secretaria de Obras e Urbanismo	Lei do Plano Diretor Municipal	["alvara","construcao","obra","reforma","urbanismo"]	fas fa-hard-hat	t	0	3	2026-05-04 13:48:59	2026-05-04 13:48:59	f	\N	\N
5	16	7	Cadastro Único (CadÚnico)	cadastro-unico-cadunico	cidadao	Inscricao no Cadastro Unico para acesso a programas sociais.	O CadUnico e a porta de entrada para mais de 30 programas sociais do governo federal, como Bolsa Familia, Tarifa Social de Energia e Minha Casa Minha Vida.	Familia com renda mensal de ate meio salario minimo por pessoa, ou ate 3 salarios minimos no total.	["CPF de todos da familia","Documento de identidade do responsavel familiar","Comprovante de residencia recente","Carteira de trabalho (se houver)"]	Ate 30 dias para inclusao no sistema federal	Gratuito	{"presencial":true,"observacoes":"Atendimento exclusivo nos CRAS"}	Secretaria de Assistencia Social	Decreto 6.135/2007	["cadunico","cadastro unico","bolsa familia","cras","beneficio"]	fas fa-id-card-alt	t	0	4	2026-05-04 13:48:59	2026-05-04 13:48:59	f	\N	\N
6	16	6	Poda de Árvore em Via Pública	poda-de-arvore-em-via-publica	cidadao	Solicitacao de poda ou supressao de arvore em area publica.	Para arvores em via publica que oferecam risco a fiacao eletrica, edificacoes ou pedestres, ou que estejam doentes. A avaliacao e feita por equipe tecnica.	Arvore em via publica (logradouros, pracas). Para arvores em area privada, e necessaria autorizacao ambiental.	["Endereco completo da arvore","Foto da arvore (opcional, agiliza)","Justificativa do pedido"]	Ate 60 dias (apos vistoria tecnica)	Gratuito	{"online":true,"telefone":"0800-000-0000"}	Secretaria de Meio Ambiente	Codigo Florestal Municipal	["poda","arvore","meio ambiente","verde","via publica"]	fas fa-tree	t	0	5	2026-05-04 13:48:59	2026-05-04 13:48:59	f	\N	\N
8	16	8	Cartão do Transporte Público	cartao-do-transporte-publico	cidadao	Emissao de cartao para uso em onibus do transporte publico.	Cartao recarregavel utilizado nos onibus do sistema de transporte publico municipal. Existem versoes comum, estudante, idoso e gratuidade.	Residir no municipio. Para gratuidades, cumprir os requisitos legais.	["Documento com foto","CPF","Comprovante de residencia","Comprovante de matricula (estudantes)","Foto 3x4"]	Ate 10 dias uteis	R$ 15,00 (primeira via comum) / Gratuito (gratuidades)	{"presencial":true}	Secretaria de Mobilidade	Estatuto do Idoso, Lei Municipal de Transporte	["transporte","onibus","cartao","passagem","gratuidade"]	fas fa-id-badge	t	0	7	2026-05-04 13:48:59	2026-05-04 13:48:59	f	\N	\N
3	16	1	Marcação de Consulta - UBS	marcacao-de-consulta-ubs	cidadao	Agendamento de consulta medica nas Unidades Basicas de Saude.	Marcacao de consultas com clinico geral, pediatra, ginecologista e outras especialidades disponiveis nas UBS do municipio.	Estar cadastrado em uma UBS do municipio (Cartao SUS).	["Cartao SUS","Documento de identidade com foto","Comprovante de residencia"]	Conforme disponibilidade da agenda	Gratuito	{"online":true,"presencial":true,"telefone":"136"}	Secretaria de Saude	Lei 8.080/90 — Lei do SUS	["saude","consulta","ubs","medico","agendamento","sus"]	fas fa-stethoscope	t	2	2	2026-05-04 13:48:59	2026-05-04 13:48:59	f	\N	\N
7	16	3	Certidão Negativa de Débitos Municipais	certidao-negativa-de-debitos-municipais	cidadao	Documento que comprova inexistencia de debitos com a prefeitura.	Documento exigido em processos de venda de imovel, abertura de empresa, financiamentos e licitacoes. Validade de 60 dias.	Estar em dia com tributos municipais (IPTU, ISS, taxas).	["CPF\\/CNPJ do solicitante"]	Imediato (online) / Ate 2 dias uteis (presencial)	Gratuito	{"online":true,"presencial":true}	Secretaria de Fazenda	Codigo Tributario Municipal	["certidao","negativa","debitos","cnd","tributo"]	fas fa-file-contract	t	1	6	2026-05-04 13:48:59	2026-05-04 13:48:59	f	\N	\N
9	16	9	Denuncia	denuncia	cidadao	O cidadão exercendo sua voz	Local apropriado para denuncias, sugestoes, visando apromiramento do serviço público prestado	Qualquer cidadao	[]	7 Dias úteis	Gratuito	{"online":true,"presencial":true,"telefone":"319999999","app":null,"observacoes":null}	Ouvidoria Municipal	aaaa	[]	fas fa-file-alt	t	7	1	2026-05-04 14:19:50	2026-05-04 14:41:42	t	153	2
11	17	10	Sugestões	sugestoes	cidadao	Espaço Destinado a Sugestões	Espaço Destinado a Sugestões para Melhorar os Serviços Prestados a População Municipal	\N	[]	5	Sim	{"online":true,"presencial":true,"telefone":null,"app":null,"observacoes":null}	Secretaria Administrativa	\N	[]	fas fa-file-alt	t	1	1	2026-05-04 16:56:38	2026-05-04 16:56:38	t	301	2
10	17	10	Denuncia	denuncia	cidadao	Canal de Denuncia do Cidadao	Canal de Denuncia do Cidadao	Qualquer Cidadão Maior de Idade	[]	5	\N	{"online":true,"presencial":true,"telefone":"Sede do Poder Legislativo","app":null,"observacoes":null}	Secretaria Administrativa da Camara Municipal	\N	[]	fas fa-file-alt	t	1	1	2026-05-04 16:54:55	2026-05-04 17:00:22	t	306	2
12	17	11	Solicitação de Informações	solicitacao-de-informacoes	cidadao	Portal de Solicitação de Informações	Solicitação de informações pública conforme lei de acesso a informação	Qualquer Cidadão	["CPF"]	5	Sim	{"online":true,"presencial":true,"telefone":null,"app":null,"observacoes":"Sede do Poder Legislativo"}	Secretaria Administrativa	Lei de Acesso a Informação	[]	fas fa-file-alt	t	0	2	2026-05-04 17:08:22	2026-05-04 17:08:33	f	305	3
1	16	3	2ª via de IPTU	2a-via-de-iptu	cidadao	Emissao da 2ª via do carne do IPTU para pagamento ou comprovacao.	Servico para emissao da segunda via do Imposto Predial e Territorial Urbano (IPTU). O contribuinte pode emitir o boleto atualizado para pagamento ou para comprovacao de regularidade fiscal.	Ser proprietario do imovel ou ter procuracao do proprietario.	["CPF do proprietario","Numero da inscricao imobiliaria ou endereco completo do imovel"]	Imediato (online) / Ate 1 dia util (presencial)	Gratuito	{"online":true,"presencial":true,"telefone":"0800-000-0000"}	Secretaria de Fazenda	Codigo Tributario Municipal	["iptu","imposto","imovel","segunda via","boleto"]	fas fa-file-invoice-dollar	t	3	0	2026-05-04 13:48:59	2026-05-04 13:48:59	f	\N	\N
\.


--
-- Data for Name: portal_solicitacao_eventos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.portal_solicitacao_eventos (id, solicitacao_id, tipo, autor_tipo, autor_nome, autor_user_id, autor_cidadao_id, status_anterior, status_novo, mensagem, created_at, updated_at) FROM stdin;
1	1	criada	cidadao	Gabriel Jardim	\N	1	\N	aberta	Solicitacao registrada pelo cidadao.	2026-05-04 14:23:40	2026-05-04 14:23:40
3	3	criada	cidadao	Gabriel Jardim	\N	1	\N	aberta	Solicitacao registrada pelo cidadao.	2026-05-04 14:45:14	2026-05-04 14:45:14
4	3	atendida	atendente	Joel Gonçalves Jardim	94	\N	aberta	atendida	Decisao no GPE Flow (deferido): Entendo perfeitamente sua insatisfaçao com os serviços prestados, peço mil desculpas e sua comunicação é muito importante para que possamos melhorar sempre	2026-05-04 15:00:18	2026-05-04 15:00:18
5	5	criada	cidadao	Gabriel Jardim	\N	1	\N	aberta	Solicitacao registrada pelo cidadao.	2026-05-04 17:24:18	2026-05-04 17:24:18
6	5	atendida	atendente	Joel Gonçalves Jardim	94	\N	aberta	atendida	Documento de decisao assinado digitalmente (DEFERIDO). decido pela concessao de informaçoes solicitadas conforme determina lei de acesso a informaçoes	2026-05-04 17:25:33	2026-05-04 17:25:33
\.


--
-- Data for Name: portal_solicitacoes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.portal_solicitacoes (id, codigo, ug_id, servico_id, cidadao_id, status, descricao, telefone_contato, email_contato, atendente_id, resposta, respondida_em, created_at, updated_at, anonima, processo_id) FROM stdin;
1	SOL-2026-00001	16	9	1	aberta	tenho encontrato muita dificuldade em conseguir usar o sistema de voces	33998088771	joeljardim@gmail.com	\N	\N	\N	2026-05-04 14:23:40	2026-05-04 14:23:40	f	\N
3	SOL-2026-00002	16	9	1	atendida	segundo teste para verificaçao da integraçao do sistema de portal cidadao com o gpeflows	33998088771	joeljardim@gmail.com	94	Entendo perfeitamente sua insatisfaçao com os serviços prestados, peço mil desculpas e sua comunicação é muito importante para que possamos melhorar sempre	2026-05-04 15:00:18	2026-05-04 14:45:14	2026-05-04 15:00:18	f	7
5	SOL-2026-00001	17	10	1	atendida	Como aplicar em produção\nResumo no README, mas em essência:\n\nBackup do banco e dos arquivos\nSobrescrever os arquivos no projeto de produção (mantendo estrutura)\nRodar: php artisan migrate --force\nLimpar cache: php artisan config:clear && route:clear && view:clear\nGarantir que o .env de produção NÃO tem GPEDOCS_PDF_DUMMY=true\nEm paralelo no GPE Docs: cadastrar sistema "gpe", tipos documentais, UG\nNo GPE: acessar Configurações → Integração GPE Docs, preencher URL/UG/Token/Webhook secret + tipos\nURL do webhook a configurar no GPE Docs: {APP_URL}/webhooks/gpedocs/receber\nO README dentro do ZIP tem o roteiro completo, incluindo como adicionar relatórios novos depois.\n\nSem dependências novas — não precisa rodar composer install (Guzzle já está no composer do GPE).	33998088771	joeljardim@gmail.com	94	decido pela concessao de informaçoes solicitadas conforme determina lei de acesso a informaçoes	2026-05-04 17:25:33	2026-05-04 17:24:18	2026-05-04 17:25:33	f	8
\.


--
-- Data for Name: proc_memorando_tramitacoes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proc_memorando_tramitacoes (id, memorando_id, tramite_origem_id, origem_usuario_id, origem_unidade_id, destino_usuario_id, destino_unidade_id, parecer, em_uso, finalizado, despachado_em, recebido_em, created_at, updated_at) FROM stdin;
1	1	\N	94	\N	\N	231	Encaminho os questionamentos ao setor responsavel	t	f	2026-05-01 14:56:50	\N	2026-05-01 14:56:50	2026-05-01 14:56:50
\.


--
-- Data for Name: proc_tipo_etapas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proc_tipo_etapas (id, tipo_processo_id, nome, descricao, ordem, tipo, setor_destino, responsavel_id, sla_horas, template_texto, obrigatorio, created_at, updated_at) FROM stdin;
3	1	Recebimento	\N	1	analise	Recursos Humanos	\N	72	Recebimento e Analise	f	2026-05-01 18:08:26	2026-05-01 18:08:26
4	1	Decisão	\N	2	despacho	Recursos Humanos	\N	72	Decisão Final	f	2026-05-01 18:08:26	2026-05-01 18:08:26
\.


--
-- Data for Name: proc_tipos_processo; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proc_tipos_processo (id, nome, descricao, sigla, categoria, schema_formulario, templates_despacho, sla_padrao_horas, ativo, criado_por, created_at, updated_at) FROM stdin;
1	Licença Prémio	Solicitação de Autorização para Tirar Licença Prémio	LPREM	administrativo	[{"tipo": "number", "campo": "Matricula", "label": "Matricula", "opcoes": null, "obrigatorio": true}, {"tipo": "number", "campo": "Documento", "label": "Documento", "opcoes": null, "obrigatorio": true}, {"tipo": "text", "campo": "Nome", "label": "Identificação", "opcoes": null, "obrigatorio": true}, {"tipo": "date", "campo": "Data", "label": "Data Solicitação", "opcoes": null, "obrigatorio": true}]	[{"nome": "Deferido", "conteudo": "A solicitação do Pedido de Goso de Férias Prémio foi Deferido nesta Data"}]	72	t	94	2026-05-01 17:11:12	2026-05-01 17:15:04
2	Denuncia Ouvidoria	Denuncia e solicitaçoes de cidadaos destinado a ouvidoria municipal	Ouv	administrativo	[]	[]	150	t	94	2026-05-04 14:41:11	2026-05-04 14:41:11
3	Solicitação Informações	Solicitação de informações através da lei de acesso a informação	E-Sic	administrativo	[]	[]	72	t	94	2026-05-04 17:07:14	2026-05-04 17:07:14
\.


--
-- Data for Name: ug_organograma; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ug_organograma (id, ug_id, parent_id, nivel, codigo, nome, ativo, created_at, updated_at, endereco_proprio, cep, logradouro, numero, complemento, bairro, cidade, uf, legado_id, legado_tipo, dt_inicio, dt_encerramento, tipo_orgao, tipo_fundo, codigo_tce, suprimir_tce, responsavel_id, protocolo_externo) FROM stdin;
165	16	\N	1	005	SECRETARIA MUNICIPAL EDUCAÇÃO	t	2026-04-30 18:08:50	2026-04-30 18:19:55	f	\N	\N	\N	\N	\N	\N	\N	6	orgao	2022-01-01	\N	Secretaria	\N	06	t	94	f
153	16	\N	1	001	GABINETE DO PREFEITO	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	2	orgao	\N	\N	\N	\N	\N	f	\N	f
154	16	153	2	001	GABINETE DO PREFEITO	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	2	unidade	\N	\N	\N	\N	\N	f	\N	f
155	16	154	3	001	Serviços Administrativos do Gabinete	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	52	departamento	\N	\N	\N	\N	\N	f	\N	f
156	16	153	2	002	COMISSÃO CONTROLE INTERNO	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	3	unidade	\N	\N	\N	\N	\N	f	\N	f
157	16	156	3	104	Controle Interno 	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	281	departamento	\N	\N	\N	\N	\N	f	\N	f
158	16	156	3	1401	PAGAMENTO DE SUBSIDIO- PREF E VICE	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	137	departamento	\N	\N	\N	\N	\N	f	\N	f
159	16	153	2	003	ASSES.IMPRENSA,RELAÇ.PUBLICAS E CERIMON.	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	4	unidade	\N	\N	\N	\N	\N	f	\N	f
160	16	159	3	101	Assessoria de Imprensa e Relaçoes Públicas e Cerimonial	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	280	departamento	\N	\N	\N	\N	\N	f	\N	f
161	16	159	3	1403	Imprensa e Comunicação	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	139	departamento	\N	\N	\N	\N	\N	f	\N	f
162	16	153	2	004	PROCURADORIA GERAL	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	5	unidade	\N	\N	\N	\N	\N	f	\N	f
163	16	162	3	050	Serviços Administrativos da Proc. Municipal	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	275	departamento	\N	\N	\N	\N	\N	f	\N	f
164	16	162	3	1025	PROCURADORIA JUR?DICA - TERC	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	298	departamento	\N	\N	\N	\N	\N	f	\N	f
166	16	165	2	001	SECRETARIA MUNICIPAL EDUCAÇÃO	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	12	unidade	\N	\N	\N	\N	\N	f	\N	f
167	16	166	3	001	MIGRAÇÃO DE DADOS	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	35	departamento	\N	\N	\N	\N	\N	f	\N	f
168	16	166	3	050	SETOR DE COMPRAS	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	263	departamento	\N	\N	\N	\N	\N	f	\N	f
169	16	166	3	106	Creches Municipais - Fundeb	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	208	departamento	\N	\N	\N	\N	\N	f	\N	f
170	16	166	3	1006	DIVIS?O ENS.- VEN/VAN	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	70	departamento	\N	\N	\N	\N	\N	f	\N	f
171	16	166	3	1007	ENS.FUNDAM.- FUNDEB - CONTR	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	295	departamento	\N	\N	\N	\N	\N	f	\N	f
172	16	166	3	1012	VEICULOS P/TRANS.ESCOLAR - CONTR	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	297	departamento	\N	\N	\N	\N	\N	f	\N	f
173	16	166	3	1013	Ensino Fundamental 	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	76	departamento	\N	\N	\N	\N	\N	f	\N	f
174	16	166	3	1016	MANUT.CRECHES - VEN/VAN	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	77	departamento	\N	\N	\N	\N	\N	f	\N	f
175	16	166	3	1114	Transporte Escolar Municipal	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	101	departamento	\N	\N	\N	\N	\N	f	\N	f
176	16	166	3	1115	SEC.EDUC.- VEN/VAN	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	102	departamento	\N	\N	\N	\N	\N	f	\N	f
177	16	166	3	1167	MANUT.RECURSOS FUNDEB 60% - VEN/VAN	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	117	departamento	\N	\N	\N	\N	\N	f	\N	f
178	16	166	3	1291	MANUT.PRE-ESCOLA - FUNDEB 60% - VEN/VAN	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	122	departamento	\N	\N	\N	\N	\N	f	\N	f
179	16	166	3	1355	MANUT.CRECHES - FUNDEB 40% - VEN/VAN	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	134	departamento	\N	\N	\N	\N	\N	f	\N	f
180	16	166	3	1412	MANUT.DIV.RECURSOS HUMANOS MATERIAIS - VEN/VAN	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	145	departamento	\N	\N	\N	\N	\N	f	\N	f
181	16	166	3	1431	MANUT.VEICULOS P/TRANS.ESCOLAR - CONTR	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	152	departamento	\N	\N	\N	\N	\N	f	\N	f
182	16	166	3	1489	SALA TELECENTRO - VEN/VAN	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	157	departamento	\N	\N	\N	\N	\N	f	\N	f
183	16	166	3	1518	MANUT.PRE-ESCOLA - FUNDEB 60% - CONTR	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	163	departamento	\N	\N	\N	\N	\N	f	\N	f
184	16	166	3	1549	MANUT.RECURSOS FUNDEB 40% - VEN/VAN	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	166	departamento	\N	\N	\N	\N	\N	f	\N	f
185	16	166	3	1552	MANUT.DIVISAO ENSINO - VEN/VAN	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	168	departamento	\N	\N	\N	\N	\N	f	\N	f
186	16	166	3	1570	PGTO.SUBS.SECRET.EDUC.- VEN/VAN	t	2026-04-30 18:08:50	2026-04-30 18:08:50	f	\N	\N	\N	\N	\N	\N	\N	175	departamento	\N	\N	\N	\N	\N	f	\N	f
187	16	166	3	1583	MANUT.CRECHES - FUNDEB 40% - CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	179	departamento	\N	\N	\N	\N	\N	f	\N	f
188	16	166	3	1585	MANUT.RECURSOS FUNDEB 40% - CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	180	departamento	\N	\N	\N	\N	\N	f	\N	f
189	16	166	3	1586	MANUT.PRE-ESCOLA - FUNDEB 40% - CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	181	departamento	\N	\N	\N	\N	\N	f	\N	f
190	16	166	3	1591	MANUT.RECURSOS FUNDEB 60% - CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	183	departamento	\N	\N	\N	\N	\N	f	\N	f
191	16	166	3	1595	MANUT.SEC.EDUC.- CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	184	departamento	\N	\N	\N	\N	\N	f	\N	f
192	16	166	3	1626	MANUT.CRECHES - FUNDEB 60% - CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	185	departamento	\N	\N	\N	\N	\N	f	\N	f
193	16	166	3	1720	MANUT.CRECHES - CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	194	departamento	\N	\N	\N	\N	\N	f	\N	f
194	16	166	3	1792	SEC.EDUC.- CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	209	departamento	\N	\N	\N	\N	\N	f	\N	f
195	16	166	3	1808	Pré Escola	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	211	departamento	\N	\N	\N	\N	\N	f	\N	f
196	16	166	3	2449	SEC.EDUC.- TERC	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	246	departamento	\N	\N	\N	\N	\N	f	\N	f
197	16	166	3	2450	SECRETARIA MUNICIPAL EDUCAÇÃO E CULTURA	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	253	departamento	\N	\N	\N	\N	\N	f	\N	f
198	16	\N	1	006	FUNDO MUNICIPAL DE SAÚDE	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	7	orgao	\N	\N	\N	\N	\N	f	\N	f
199	16	198	2	001	FUNDO MUNICIPAL DE SAÚDE	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	13	unidade	\N	\N	\N	\N	\N	f	\N	f
200	16	199	3	001	MIGRAÇÃO DE DADOS	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	36	departamento	\N	\N	\N	\N	\N	f	\N	f
201	16	199	3	050	Serviços Administrativos do FMS	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	260	departamento	\N	\N	\N	\N	\N	f	\N	f
202	16	199	3	100	Vigilância Epidemiológica	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	155	departamento	\N	\N	\N	\N	\N	f	\N	f
203	16	199	3	1002	SEC.SA?DE - TERC	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	292	departamento	\N	\N	\N	\N	\N	f	\N	f
204	16	199	3	1003	VIG. EPID.- CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	293	departamento	\N	\N	\N	\N	\N	f	\N	f
205	16	199	3	1005	LABORAT?RIO MUN. - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	69	departamento	\N	\N	\N	\N	\N	f	\N	f
206	16	199	3	1006	PROG.SAUDE BUCAL - TERC	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	294	departamento	\N	\N	\N	\N	\N	f	\N	f
207	16	199	3	1007	MANUT.POLICL?NICA MUN. - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	71	departamento	\N	\N	\N	\N	\N	f	\N	f
208	16	199	3	1010	VIG. EPID.- TERC	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	296	departamento	\N	\N	\N	\N	\N	f	\N	f
209	16	199	3	1019	SEC.SA?DE - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	79	departamento	\N	\N	\N	\N	\N	f	\N	f
210	16	199	3	1020	PROG.SAUDE BUCAL - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	80	departamento	\N	\N	\N	\N	\N	f	\N	f
211	16	199	3	1022	MANUT.POLICLINICA MUN. - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	82	departamento	\N	\N	\N	\N	\N	f	\N	f
212	16	199	3	1023	Vigilância e Inspenção Sanitária	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	83	departamento	\N	\N	\N	\N	\N	f	\N	f
213	16	199	3	1042	DIVIS?O FISIOTERAPIA - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	90	departamento	\N	\N	\N	\N	\N	f	\N	f
214	16	199	3	1046	ESF-ESTRAT?GIA SAUDE FAMILIA - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	92	departamento	\N	\N	\N	\N	\N	f	\N	f
215	16	199	3	1061	ESF-ESTRAT?GIA SAUDE FAMILIA 15% - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	95	departamento	\N	\N	\N	\N	\N	f	\N	f
216	16	199	3	1109	MANUT.PSF - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	100	departamento	\N	\N	\N	\N	\N	f	\N	f
217	16	199	3	1123	SECR SAUDE ADMINISTRA??O	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	107	departamento	\N	\N	\N	\N	\N	f	\N	f
218	16	199	3	1151	FARM?CIA TODOS REMUME - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	113	departamento	\N	\N	\N	\N	\N	f	\N	f
219	16	199	3	1293	ESF-ESTRAT?GIA SAUDE FAMILIA - CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	123	departamento	\N	\N	\N	\N	\N	f	\N	f
220	16	199	3	1310	PSF - CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	124	departamento	\N	\N	\N	\N	\N	f	\N	f
221	16	199	3	1318	MANUT.PSF - CONTR (BLATB)	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	125	departamento	\N	\N	\N	\N	\N	f	\N	f
222	16	199	3	1319	MANUT.DEPARTAMENTO A??ES SA?DE - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	126	departamento	\N	\N	\N	\N	\N	f	\N	f
223	16	199	3	1384	MANUT.PROG.SAUDE BUCAL - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	135	departamento	\N	\N	\N	\N	\N	f	\N	f
224	16	199	3	1425	ESF-ESTRAT?GIA SAUDE FAMILIA SUS - CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	149	departamento	\N	\N	\N	\N	\N	f	\N	f
225	16	199	3	1471	MANUT.POLICLINICA MUN. - CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	154	departamento	\N	\N	\N	\N	\N	f	\N	f
226	16	199	3	1573	MANUT.SEC.SAUDE - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	176	departamento	\N	\N	\N	\N	\N	f	\N	f
227	16	199	3	1574	MANUT.VIG. EPID.- CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	177	departamento	\N	\N	\N	\N	\N	f	\N	f
228	16	199	3	1692	MANUT.PSF - CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	191	departamento	\N	\N	\N	\N	\N	f	\N	f
229	16	199	3	1735	MANUT.NASF-NUCLEO APOIO SAUDE FAMILIA - CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	197	departamento	\N	\N	\N	\N	\N	f	\N	f
230	16	199	3	1745	FARM?CIA TODOS REMUME15% - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	201	departamento	\N	\N	\N	\N	\N	f	\N	f
231	16	199	3	1972	FARM?CIA TODOS REMUME - TERC	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	222	departamento	\N	\N	\N	\N	\N	f	\N	f
232	16	199	3	2046	MANUT.POLICL?NICA MUN. - CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	227	departamento	\N	\N	\N	\N	\N	f	\N	f
233	16	199	3	2345	PGTO.SUBS.SECRET.SA?DE - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	238	departamento	\N	\N	\N	\N	\N	f	\N	f
234	16	199	3	2424	PROG.SAUDE BUCAL - CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	244	departamento	\N	\N	\N	\N	\N	f	\N	f
235	16	199	3	2433	ESF-ESTRAT?GIA SAUDE DA FAMILIA - CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	245	departamento	\N	\N	\N	\N	\N	f	\N	f
236	16	199	3	2459	LABORAT?RIO MUN. - CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	248	departamento	\N	\N	\N	\N	\N	f	\N	f
237	16	199	3	2460	FUNDO MUNICIPAL DE SAÚDE	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	278	departamento	\N	\N	\N	\N	\N	f	\N	f
238	16	\N	1	009	SECRETARIA MUNIC. ESPORTE E LAZER	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	10	orgao	\N	\N	\N	\N	\N	f	\N	f
239	16	238	2	001	SECRETARIA MUNIC. ESPORTE E LAZER	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	17	unidade	\N	\N	\N	\N	\N	f	\N	f
240	16	239	3	001	MIGRAÇÃO DE DADOS	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	38	departamento	\N	\N	\N	\N	\N	f	\N	f
241	16	239	3	050	SETOR DE COMPRAS	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	257	departamento	\N	\N	\N	\N	\N	f	\N	f
242	16	239	3	100	Academias ao Ar Livre	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	217	departamento	\N	\N	\N	\N	\N	f	\N	f
243	16	239	3	1030	SEC.ESPORTE LAZER - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	300	departamento	\N	\N	\N	\N	\N	f	\N	f
244	16	239	3	1142	ESPORTE AMADOR - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	111	departamento	\N	\N	\N	\N	\N	f	\N	f
245	16	239	3	1255	QUADRAS POLIESPORTIVAS - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	120	departamento	\N	\N	\N	\N	\N	f	\N	f
246	16	239	3	1557	MANUT.ACADEMIAS AO AR LIVRE - CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	170	departamento	\N	\N	\N	\N	\N	f	\N	f
247	16	239	3	1730	ESPORTE AMADOR - CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	196	departamento	\N	\N	\N	\N	\N	f	\N	f
248	16	239	3	1757	SEC.ESPORTE LAZER - TERC	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	204	departamento	\N	\N	\N	\N	\N	f	\N	f
249	16	239	3	2176	QUADRAS POLIESPORTIVAS - CONTR	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	232	departamento	\N	\N	\N	\N	\N	f	\N	f
250	16	239	3	2608	PGTO.SUBS.SECR.ESPORTE LAZER - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	251	departamento	\N	\N	\N	\N	\N	f	\N	f
251	16	239	3	2609	SECRETARIA MUNIC. ESPORTE E LAZER	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	279	departamento	\N	\N	\N	\N	\N	f	\N	f
252	16	\N	1	020	SECRETARIA MUNICIPAL DE PLANEJAMENTO E GESTÃO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	16	orgao	\N	\N	\N	\N	\N	f	\N	f
253	16	252	2	001	SERVIÇOS ADM. DA SEC. DE PLANEJAMENTO E GESTÃO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	32	unidade	\N	\N	\N	\N	\N	f	\N	f
254	16	253	3	001	SERVIÇOS ADMINISTRATIVOS DA SECRETARIA DE PLANEJAMENTO E GESTÃO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	307	departamento	\N	\N	\N	\N	\N	f	\N	f
255	16	252	2	002	DEPARTAMENTO REC. HUMANOS SEC. PLANEJ. E GESTÃO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	33	unidade	\N	\N	\N	\N	\N	f	\N	f
256	16	255	3	001	DEPARTAMENTO RECURSOS HUMANOS SEC. PLANEJAMENTO E GESTÃO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	313	departamento	\N	\N	\N	\N	\N	f	\N	f
257	16	252	2	003	DEP. RECURSOS MATERIAIS SEC. PLANEJAMENTO E GESTÃO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	34	unidade	\N	\N	\N	\N	\N	f	\N	f
258	16	257	3	001	DEPARTAMENTO RECURSOS MATERIAIS SEC. PLANEJAMENTO E GESTÃO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	327	departamento	\N	\N	\N	\N	\N	f	\N	f
259	16	\N	1	030	SECRETARIA MUNICIPAL DE GOVERNO E FINANÇAS - SEGOV	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	17	orgao	\N	\N	\N	\N	\N	f	\N	f
260	16	259	2	001	SERVIÇOS ADM. DA SECRETARIA - SEGOV	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	35	unidade	\N	\N	\N	\N	\N	f	\N	f
261	16	260	3	001	SERVIÇOS ADMINISTRATIVOS DA SECRETARIA - SEGOV	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	308	departamento	\N	\N	\N	\N	\N	f	\N	f
262	16	259	2	002	DEPARTAMENTO DE CONTABILIDADE - SEGOV	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	36	unidade	\N	\N	\N	\N	\N	f	\N	f
263	16	262	3	001	DEPARTAMENTO DE CONTABILIDADE - SEGOV	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	314	departamento	\N	\N	\N	\N	\N	f	\N	f
264	16	259	2	003	DIVISÃO DE GESTÃO DOC. E ARQUIVO PÚBL - SEGOV	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	37	unidade	\N	\N	\N	\N	\N	f	\N	f
265	16	264	3	001	DIVISÃO DE GESTÃO DOCUMENTAL E ARQUIVO PÚBLICO - SEGOV	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	315	departamento	\N	\N	\N	\N	\N	f	\N	f
266	16	\N	1	050	SECRETARIA MUNICIPAL CULTURA E TURISMO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	18	orgao	\N	\N	\N	\N	\N	f	\N	f
267	16	266	2	001	SECRETARIA MUNICIPAL CULTURA E TURISMO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	38	unidade	\N	\N	\N	\N	\N	f	\N	f
268	16	267	3	001	DIVISÃO DE CULTURA SEC. CULTURA E TURISMO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	316	departamento	\N	\N	\N	\N	\N	f	\N	f
269	16	266	2	002	FUMPAC-FUNDO MUN. PROTEÇÃO PATRIMONIO CULTURAL	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	39	unidade	\N	\N	\N	\N	\N	f	\N	f
270	16	269	3	001	 FUMPAC-FUNDO MUN. PROTEÇÃO PATRIMONIO CULTURAL	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	328	departamento	\N	\N	\N	\N	\N	f	\N	f
271	16	\N	1	070	SECRETARIA MUNICIPAL DE DESENVOLVIMENTO URBANO E RURAL	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	19	orgao	\N	\N	\N	\N	\N	f	\N	f
272	16	271	2	001	SERVIÇOS ADM. DA SECRTARIA - URBANO E RURAL	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	40	unidade	\N	\N	\N	\N	\N	f	\N	f
273	16	272	3	001	SERVIÇOS ADMINISTRATIVOS DA SECRTARIA - URBANO E RURAL	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	318	departamento	\N	\N	\N	\N	\N	f	\N	f
274	16	271	2	002	DIVISÃO DE OBRAS PÚBLICAS E URBANISMO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	41	unidade	\N	\N	\N	\N	\N	f	\N	f
275	16	274	3	001	DIVISÃO DE OBRAS PÚBLICAS E URBANISMO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	319	departamento	\N	\N	\N	\N	\N	f	\N	f
276	16	271	2	003	DIVISÃO DE TRÂNSITO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	42	unidade	\N	\N	\N	\N	\N	f	\N	f
277	16	276	3	001	DIVISÃO DE TRÂNSITO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	320	departamento	\N	\N	\N	\N	\N	f	\N	f
278	16	271	2	004	DIVISÃO DE SERVIÇOS RURAIS	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	43	unidade	\N	\N	\N	\N	\N	f	\N	f
279	16	278	3	001	DIVISÃO DE SERVIÇOS RURAIS	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	321	departamento	\N	\N	\N	\N	\N	f	\N	f
280	16	\N	1	080	SECRETARIA MUNICIPAL DESENVOLVIMENTO ECONOMICO, ENPREENDORISMO E AGRONEGOCIO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	20	orgao	\N	\N	\N	\N	\N	f	\N	f
281	16	280	2	001	SERVIÇOS ADM. DA SECRETARIA - DESENV. ECONOMICO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	44	unidade	\N	\N	\N	\N	\N	f	\N	f
282	16	281	3	001	SERVIÇOS ADMINISTRATIVOS DA SECRETARIA - DESENVOLVIMENTO ECONOMICO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	309	departamento	\N	\N	\N	\N	\N	f	\N	f
283	16	280	2	002	DEPARTAMENTO DE COMÉRCIO, E INDUSTRIA 	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	45	unidade	\N	\N	\N	\N	\N	f	\N	f
284	16	283	3	001	DEPARTAMENTO DE COMÉRCIO, E INDUSTRIA 	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	322	departamento	\N	\N	\N	\N	\N	f	\N	f
285	16	280	2	003	EMPREENDORISMO E AGRONEGOCIO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	46	unidade	\N	\N	\N	\N	\N	f	\N	f
286	16	285	3	001	EMPREENDORISMO E AGRONEGOCIO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	323	departamento	\N	\N	\N	\N	\N	f	\N	f
287	16	\N	1	100	FUNDO MUNICIPAL DE ASSISTÊNCIA SOCIAL	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	21	orgao	\N	\N	\N	\N	\N	f	\N	f
288	16	287	2	001	FUNDO MUNICIPAL DE ASSISTÊNCIA SOCIAL	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	47	unidade	\N	\N	\N	\N	\N	f	\N	f
289	16	288	3	001	FUNDO MUNICIPAL DE ASSISTÊNCIA SOCIAL	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	329	departamento	\N	\N	\N	\N	\N	f	\N	f
290	16	287	2	002	FUNDO MUN.DIREITOS CRIANÇA/ADOLESCENTE	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	48	unidade	\N	\N	\N	\N	\N	f	\N	f
291	16	290	3	001	 FUNDO MUN.DIREITOS CRIANÇA/ADOLESCENTE	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	330	departamento	\N	\N	\N	\N	\N	f	\N	f
292	16	287	2	003	FUNDO MUNICIPAL DO IDOSO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	51	unidade	\N	\N	\N	\N	\N	f	\N	f
293	16	292	3	001	FUNDO MUNICIPAL DO IDOSO	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	331	departamento	\N	\N	\N	\N	\N	f	\N	f
294	16	\N	1	110	SECRETARIA MUNICIPAL DE MEIO AMBIENTE E DESENVOLVIMENTO SUSTENTAVEL	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	22	orgao	\N	\N	\N	\N	\N	f	\N	f
295	16	294	2	001	SERVIÇOS ADM. DA SECRETARIA - MEIO AMBIENTE 	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	49	unidade	\N	\N	\N	\N	\N	f	\N	f
296	16	295	3	001	SERVIÇOS ADMINISTRATIVOS DA SECRETARIA - MEIO AMBIENTE 	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	310	departamento	\N	\N	\N	\N	\N	f	\N	f
297	16	\N	1	120	SECRETARIA MUNICIPAL DE TECNOLOGIA DA INFORMAÇÃO E INOVAÇÃO - SETINF	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	23	orgao	\N	\N	\N	\N	\N	f	\N	f
298	16	297	2	001	SERVIÇOS ADM. DA SECRETARIA - SETINF	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	50	unidade	\N	\N	\N	\N	\N	f	\N	f
299	16	298	3	001	SERVIÇOS ADMINISTRATIVOS DA SECRETARIA - SETINF	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	311	departamento	\N	\N	\N	\N	\N	f	\N	f
300	17	\N	1	001	GABINETE E SECRETARIA DA CÂMARA	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	1	orgao	\N	\N	\N	\N	\N	f	\N	f
301	17	300	2	001	GABINETE E SECRETARIA DA CÂMARA	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	1	unidade	\N	\N	\N	\N	\N	f	\N	f
302	17	301	3	1028	SERV.GABINETE - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	85	departamento	\N	\N	\N	\N	\N	f	\N	f
303	17	301	3	1402	PAGAMENTO SUBS.S PREFEITO/VICE - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	138	departamento	\N	\N	\N	\N	\N	f	\N	f
304	17	301	3	2036	SERV. GABINETE - VEN/VAN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	226	departamento	\N	\N	\N	\N	\N	f	\N	f
305	17	301	3	9999	Gabinete do Prefeito - Vice Prefeito	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	1	departamento	\N	\N	\N	\N	\N	f	\N	f
306	17	301	3	10000	GABINETE E SECRETARIA DA CÂMARA	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	303	departamento	\N	\N	\N	\N	\N	f	\N	f
307	18	\N	1	001	FUNPREV	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	15	orgao	\N	\N	\N	\N	\N	f	\N	f
308	18	\N	1	003	FUNDO PREVIDENCIÁRIO MUN.DE PARAGUACU	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	14	orgao	\N	\N	\N	\N	\N	f	\N	f
309	18	308	2	001	FUNDO PREVIDENCIÁRIO MUN.DE PARAGUACU FUNFIN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	29	unidade	\N	\N	\N	\N	\N	f	\N	f
310	18	309	3	001	FUNDO PREVIDENCIÁRIO MUN.DE PARAGUACU FUNFIN	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	304	departamento	\N	\N	\N	\N	\N	f	\N	f
311	18	308	2	002	FUNDO PREVIDENCIARIO MUN.DE PARAGUACU FUNPREV	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	31	unidade	\N	\N	\N	\N	\N	f	\N	f
312	18	311	3	001	FUNDO PREVIDENCIARIO MUN.DE PARAGUACU FUNPREV	t	2026-04-30 18:08:51	2026-04-30 18:08:51	f	\N	\N	\N	\N	\N	\N	\N	305	departamento	\N	\N	\N	\N	\N	f	\N	f
\.


--
-- Data for Name: ugs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ugs (id, codigo, nome, cnpj, nivel_1_label, nivel_2_label, nivel_3_label, ativo, observacoes, created_at, updated_at, cep, logradouro, numero, complemento, bairro, cidade, uf, legado_orgao_id, brasao_path, telefone, email_institucional, site, portal_slug, banner_path, banner_titulo, banner_subtitulo, banner_link_url, banner_link_label, banner_ativo) FROM stdin;
17	UG-002	CAMARA MUNICIPAL DE PARAGUAÇU	07.480.746/0001-99	Órgão	Unidade	Departamento	t	Importado de gpdparaguacu — gestora_id=2, poder=Legislativo Municipal, codigo_tce=2	2026-04-30 18:08:51	2026-05-04 18:51:14	\N	\N	20	PREDIO	\N	\N	\N	2	brasoes/ug-002-196f47.jpg	\N	\N	\N	cmparaguacu	banners/ug-002-0da2b8.png	teste 1	teste 2	\N	\N	t
18	UG-003	FUNDO PREVIDENCIÁRIO MUNICIPAL	41.774.159/0001-40	Órgão	Unidade	Departamento	t	Importado de gpdparaguacu — gestora_id=3, poder=Autarquia Municipal, codigo_tce=3	2026-04-30 18:08:51	2026-04-30 18:08:51	\N	\N	148	SALA 101 SALA 202	\N	\N	\N	3	\N	\N	\N	\N	ug-003	\N	\N	\N	\N	\N	t
16	UG-001	PREFEITURA MUNICIPAL DE PARAGUACU - MG	18.008.193/0001-92	Unidade	Subunidade	Setor	t	Importado de gpdparaguacu — gestora_id=1, poder=Executivo Municipal, codigo_tce=1	2026-04-30 18:08:50	2026-05-04 14:09:24	37120-000	Rua Edward Eustaquio de Andrade	220	\N	Centro	Paraguacu	MG	1	brasoes/paraguacu.jpg	(35) 3267-1155 - (35) 3267-1888	\N	www.paraguacu.mg.gov.br	pmparaguacu	\N	\N	\N	\N	\N	t
\.


--
-- Data for Name: user_ugs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.user_ugs (id, user_id, ug_id, principal, created_at, updated_at) FROM stdin;
2	133	17	t	2026-04-30 18:28:44	2026-04-30 18:28:44
3	158	17	t	2026-04-30 18:28:44	2026-04-30 18:28:44
4	166	17	t	2026-04-30 18:28:44	2026-04-30 18:28:44
8	189	16	t	2026-04-30 20:31:56	2026-04-30 20:31:56
9	189	17	f	2026-04-30 20:31:56	2026-04-30 20:31:56
10	189	18	f	2026-04-30 20:31:56	2026-04-30 20:31:56
1	94	16	t	2026-04-30 18:28:44	2026-05-01 14:13:50
5	94	17	f	2026-04-30 18:38:21	2026-05-01 14:13:50
7	94	18	f	2026-04-30 18:39:30	2026-05-01 14:13:50
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, cpf, tipo, ug_id, unidade_id, legado_usuario_id, super_admin, acesso_geral_ug) FROM stdin;
190	Signatario 024...19	gabinete@paraguacu.mg.gov.br	\N	$2y$12$f/Qdhx7HhVnNa6GdXtdedexyQsaRC.mihWYsn4keeZroweiwIe1ou	\N	2026-05-02 20:44:22	2026-05-02 20:44:22	02461096619	externo	16	\N	\N	f	f
94	Joel Gonçalves Jardim	joeljardim@gmail.com	\N	$2y$12$OITnw2VdOG1n/ag0cQc6puAaLn4gOTuxIU0aj3H0uKr7LhYY7cx8y	\N	2026-04-30 18:02:23	2026-05-01 14:13:49	85183865604	interno	16	\N	\N	t	t
118	GUILHERME REIS MOTERANI	concurso@paraguacu.mg.gov.br	\N	$2y$10$RzujAQ6qpG3gefL.g7nsyOEZEh4ZMbMORMb0GUPIuQF/krW/ZFA0q	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	08015077664	interno	16	\N	10046	f	f
119	HEULLER CLAUDIO FERNANDES	heuller.sagp@gmail.com	\N	$2y$10$q/2F6ZJn551ht4sFWiBB/OS8QFUzoSoIXkKkoWH1frElFgnJ0xoF.	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	00360644678	interno	16	\N	10065	f	f
120	IDELIN DA CRUZ LOPES	idelinc@yahoo.com.br	\N	$2y$10$6vNmOY2Itx4QJFIOYkW4rOqOQN9M818CnTIk03kGaRAWONPgC4kji	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	05486835655	interno	16	\N	10049	f	f
121	IGOR RONALD GONÇALVES FREIRE	igor092@hotmail.com	\N	$2y$10$69Dsp8ElB6lQ3URF8UuucONGlRKJ230twxSwsOicHGtVS4bZedOX.	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	11436589657	interno	16	\N	10008	f	f
122	IGOR SILVA AGUILAR	igor.aguilarcm98@gmail.com	\N	$2y$10$p28EQsDvKiVzTgALYG72wuD2rB2cQmGIcDi59mjiye6tPyHONyDGC	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	14768713610	interno	16	\N	10090	f	f
123	JENNYFER PASSOS PEREIRA	jennyferpp0510@icloud.com	\N	$2y$10$FEBpoRmsaV7nwd/1RdwhaOY3CYoRWZ262tWF6SvjmO2XAOlZJGgN.	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	14338701692	interno	16	\N	10057	f	f
124	JIVANILDO DE PAULA GONÇALVES	jivanildo.goncalves@educacao.mg.gov.br	\N	$2y$10$Fmt2Np6oRrDQdLr.PNlm/eN9VJlz24wJQyFYNSfvQeIZnvC8eZx5a	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	94264350697	interno	16	\N	10069	f	f
126	JONAS SOARES MARTINS	jonas@conceito.com	\N	$2y$10$S1Exmu78TuOaPmvcHpT9Bu8uKYWoASuLyOePf2CwNva1CYDt9t4tW	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	16377126697	interno	16	\N	10083	f	f
127	JONATAN PEREIRA DOS SANTOS CARVALHO	jonatan.sagp@gmail.com	\N	$2y$10$cR5egzvwMU/a5ns7bTOi4un.saoA0WprAfpbXt.YrFQfxjia3ApAi	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	01524742694	interno	16	\N	10062	f	f
128	JOSÉ DEOLINDO ALVES	jd.alves@hotmail.com	\N	$2y$10$SPqiGUhi6z4eOpYtx.1CbeGsK6bHooKTli4GoQ9okXT3l8qcxT9ae	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	15692458861	interno	16	\N	10061	f	f
129	JOSE HOMERO LOPES	jhomerolopes20@gmail.com	\N	$2y$10$7ggHCJViZyLvXjp0Gc2lvet6wiRM/8uc7b7oUaiknAw.YSs1qd0n6	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	46780467634	interno	16	\N	10039	f	f
130	JOSE LUIZ COSTA CASTILHO	joseluizcostacastilho@hotmail.com	\N	$2y$10$aA6ubauveQFRwrHALDxpGu4TxNosW67L.4tXfWod0BLKp7mkUL7mS	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	11151618659	interno	16	\N	10120	f	f
131	JOSÉ MARIA NETO	netinho340@gmail.com	\N	$2y$10$NtEdq.rlNbod1Ca5yH7foOau9EX0PBvIazeCThvnDT5EiamjsqGKG	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	12570335657	interno	16	\N	10075	f	f
132	JULIANA BARBOSA BUENO BAPTISTA	julianapoliclinica@hotmail.com	\N	$2y$10$lh2i2Ja2CkcUOoGi7L0Fnesi53hnT83znITPBgvBiemPYhI1EuUzC	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	03286706698	interno	16	\N	10017	f	f
134	KALYNE BERNARDES DE MORAIS	kalynebernardes@hotmail.com	\N	$2y$10$WaREnOPzUrnCNiBp8lVRUeDYAr4f3QO1r7MFsEvBrnx0XoMJyrKMG	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	09484830676	interno	16	\N	10064	f	f
135	KLEYTON PORTES LIMA	kleyton@gmail.com	\N	$2y$10$fNQuqf9mk4BGAJYYxi/LwuUZprLTWfNkHM31cnPBdHUqhQWxcyFeS	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	04154736671	interno	16	\N	10106	f	f
136	KLINGER SOARES DIAS	KLINGERDIAS@HOTMAIL.COM	\N	$2y$10$ZrthEi/c3KZXuMJtw5Regu1SYLf8BMrHBZnukBP./mgg6zOBJqGQK	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	10735740674	interno	16	\N	10059	f	f
137	LEANDRO DIAS PEREIRA	leandrodp25@hotmail.com	\N	$2y$10$cEg/I0SFFa2Fu5/9.RSg3uHWh6gbcCXAwWoqnx.SDd7pzecKswkpG	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	07705912643	interno	16	\N	10026	f	f
138	LEONARDO GONCALVES FREIRE	leonardo@conceitogestaopublica.com.br	\N	$2y$10$lK3LhjE0kOM3PAb43h4kGuzxw719D.ePmDuVTI.da2vprU5WTIp1a	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	12046674626	interno	16	\N	10005	f	f
139	LETICIA VENCESLAU RAMOS	leticiavenceslaur@gmail.com	\N	$2y$10$1wZrDLzxyg.3nYgxtaCCpe76mkUf.EpPNvO43ZgeBQhivyuZsv5S6	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	11869433602	interno	16	\N	10035	f	f
140	LINDOMAR ALVES BRAGANÇA	lindomar@gmail.com	\N	$2y$10$6ZHJ9DowNU.Pn4p.p4iUw.2ecJyxC0PKcxhPuAdzXdsfdZn.ELuIK	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	00388224665	interno	16	\N	10053	f	f
141	LUIZ FELIPE DOS SANTOS	compras.pmpparaguassu@hotmail.com	\N	$2y$10$O0Qi.sgEK0dJmfg7va3kbOcMnwadVOy59aQlFGsOsBmn54/Ozsm7e	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	11560022655	interno	16	\N	10015	f	f
142	MARCELO SANTANA AGOSTINHO PADILHA	Consultoria@metamunicipal.com	\N	$2y$10$qodJ53Ep1z03xz0vFFPAPuGyw4OVsdmq24WwCICBzn0A3QtoPvmni	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	06650578657	interno	16	\N	10111	f	f
143	MARCIA MARQUES	carolina.secretariadesaude@gmail.com	\N	$2y$10$H5HQDStf2QMTNymftK/ddOyD1blS6PNP.ZHD6de8KuEy5QfF.xeAa	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	30418619840	interno	16	\N	10088	f	f
144	MARCIO GUSTAVO ALVES AMARAL	marciogustavoalves8@gmail.com	\N	$2y$10$4DMxrhSsEn0xsAGWJ5Lnduaf0Wlc5ulIPrsXBRJi1aWrmu9.hBReG	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	02303767628	interno	16	\N	10099	f	f
145	MARCO ANTÔNIO PINTO	marco.antonio.pnt1@gmail.com	\N	$2y$10$4BC4qh8V28yLnmibMQPJX.v648vrh6tY3jEeohVFVELx/Jol/4Vcq	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	85515671600	interno	16	\N	10081	f	f
146	MARIA INES MACHADO SILVA	pedrohenriqueromanhol975@gmail.com	\N	$2y$10$544b6fegDt9ZhYAnpWBjOOxycwI.w5GyAufiqj728S9l6S43FwC02	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	\N	interno	16	\N	10091	f	f
147	MARILIA DE SOUZA CARNEIRO	mariliasc88@hotmail.com	\N	$2y$10$ieLMA5ETW5uMqAUUsyOEDOrm.0W7HaiMjMFfxJdW/bzVoNT2U/eO6	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	09721654604	interno	16	\N	10038	f	f
189	Maria Teste	teste@gpedocs.local	\N	$2y$12$42m5FUuGLr8/3zBRbeDmZOC1VVGWRhcNDA0pT2NO/hZv6RU49C4b6	\N	2026-04-30 20:31:56	2026-04-30 20:31:56	11122233344	interno	16	\N	\N	t	f
148	MARQUIELE AZEVEDO	marquiele@gmail.com	\N	$2y$10$kiD3ZvgtLbYLxC/xCdmGqOwSCNU0LPBdY9yMruqLu/Pj6kurWOiOm	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	09402198679	interno	16	\N	10074	f	f
149	MATHEUS FELIPE OLIVEIRA DOS SANTOS	matheus.sagp@gmail.com	\N	$2y$10$0HReqQTesZAgaKC3KhWOdei/7pNfscz29MqViOmPYFLBxCvM3sqqC	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	13719454622	interno	16	\N	10087	f	f
150	MUNICIPIO DE PARAGUAÇU	user_portal@conceito.com.br	\N	$2y$10$803O6m/N671aqzXYohC6.O6TInQvnjyKU/nd4UzSnxsCEam1F7FcW	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	\N	interno	16	\N	10001	f	f
151	NAILENE GONCALVES MARQUES	A@HOTMAIL.COM	\N	$2y$10$vOYr1nwSHZne0WWQgImOTuVdiuNEJkM9mEtkXpi2xBTNBOnNwETM6	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	91860164668	interno	16	\N	10121	f	f
152	NAIRA LIMA MARCAL	nairamarcal@gmail.com	\N	$2y$10$71bz7FeXRZumjZiLJ/b2HOrbaz6twif1q5uo8M1AhEjGshSFz9GDS	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	09014525648	interno	16	\N	10073	f	f
153	PALOMA CHAVES ALMEIDA	palomachaves0@gmail.com	\N	$2y$10$YwuVkMer9IxAA/2wAsaXCuXCNkW2UiQ54x5Sjd4yG6XREE.dy5.4G	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	10854933697	interno	16	\N	10102	f	f
154	PEDRO HENRIQUE JARDIM	pedrohenrique54062@gmail.com	\N	$2y$10$ENK7psHtvYynl3ZA36wqP.kgOkmM92zOYu9Ha91aqZycJvD.8zAfS	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	14034287608	interno	16	\N	10067	f	f
155	PEDRO HENRIQUE ROMANHOL PESSOA	pedrohomanhol975@gmail.com	\N	$2y$10$zvWcjYuEObSQ3pQzFtnpp.tdTX8RvVmkh/Hp2D8y35qeD8rVY8qx6	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	12978312688	interno	16	\N	10092	f	f
156	POLIANA SODRÉ HERCULANO	poliana@poliana.com	\N	$2y$10$Ij8o7t271Sp0ofOhjy3NL.io1JgpH58XbXB709b4tXLhr/AfaNwcy	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	14296218697	interno	16	\N	10084	f	f
157	PRISCILA APARECIDA DA SILVA	silvapriscila005@gmail.com	\N	$2y$10$HklkjDsDOkhWpSJfy7uVC.pXolaazlNmLIJ/IS0WasqaeExrSYATe	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	13504915609	interno	16	\N	10070	f	f
159	RAFAELA APARECIDA MARINHO CALDEIRA	rafaelamarinhocaldeira@gmail.com	\N	$2y$10$Iw5Vj6qG8yirigv00nLnSe4vUzUbrGxgm1yyLgxkIXPkqbIKeXW7.	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	12691958671	interno	16	\N	10086	f	f
160	RAPHAEL BATISTA SOUZA	engraphael.versa@gmail.com	\N	$2y$10$Ur1Kf08vTl1OevkmcjfgJewamCTIvM5sMl8r2SwCX2DYx05Bx.vou	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	08034757640	interno	16	\N	10109	f	f
161	REGIANE ELIAS DE MIRANDA	REGIANEMIRADV@GMAIL.COM	\N	$2y$10$PYtSCopOaiCvxU7DRUrDeOK.VD5Li5252Q9M4tJHRdJqVLGoGPFQK	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	91863201653	interno	16	\N	10066	f	f
162	RENAN PEREIRA ARAUJO	renanpa08@gmail.com	\N	$2y$10$bDcPAsjv.3TwKZtmopoaOuugA3zmznJTd7RurbOntf.CTYsGqL9F2	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	14877977619	interno	16	\N	10104	f	f
163	RITA DE CASSIA CAMPOS PEREIRA E SILVA	ritac.campos@hotmail.com	\N	$2y$10$8.8jbthoEfxbVQerqlEgN.2TAy7DLBePcLTAxSIcL6RZQXHSITKau	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	89042859687	interno	16	\N	10042	f	f
164	RODRIGO ELIAS SILVA	rodrigoelias19@gmail.com	\N	$2y$10$JEz9t/9iDOJ9ofiPaF92cubMQ5bLppeNcm95QiiD4ByGRr26DFDGi	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	11568041675	interno	16	\N	10103	f	f
165	RONILDO SAÚDE DE SOUSA	sousaronildo400@gmail.com	\N	$2y$10$AhX1z8uDSjjSEbtAE7XYTOT3uuyf9iOlY6EEDilN8fmEF.wTzs6VC	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	14498205650	interno	16	\N	10113	f	f
167	SEBASTIAO LUCIANO RODRIGUES	contabilidade.paraguacu20@gamil.com	\N	$2y$10$mAc.NHFUUNtrZnskp.jPju6c5QN0XlLQvCRg5HVWBbweV5P1Le.xa	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	34623590615	interno	16	\N	10018	f	f
168	SELENA SILVA DE ARAUJO	contabilidadecamarapcu@yahoo.com.br	\N	$2y$10$tP0xSiFoT0kbQdI.Zc3B9uetI8Na0Exo9jlrnyAD.RFogK264yZGe	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	41264819668	interno	16	\N	10060	f	f
169	TAIS CAROLINA ROSA DOS SANTOS	tais@tais.com	\N	$2y$10$z3dsA9RWJ1Ug4koSwyAGY.i2H2ZdVO3hTt17N.CkJF8K.SubLrTOW	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	02092419692	interno	16	\N	10089	f	f
170	TAÍS RODRIGUES PEREIRA	taisrodriguesspereira@gmail.com	\N	$2y$10$yz4D7L.D3CBzSFmIfsukfeS9Bb9LU1XHSm7OFYc8/iQ0W0U3Ie4Qa	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	01889015660	interno	16	\N	10071	f	f
171	TALLES RIBEIRO DE CASTILHO	talles@talles.com	\N	$2y$10$0bwjITi4N.NNoL0bZ4z3POU1eGqKntGkBkhhS0A1bL43d5YWg82jG	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	08943254695	interno	16	\N	10100	f	f
172	TANIA CRISTINA GONÇALVES	tnacgoncalves@yahoo.com.br	\N	$2y$10$abnObIdq0mJUJRM/ydRMpODiWImu5ciSyrJpNGVFrbWvFS8ybZifG	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	78796989653	interno	16	\N	10068	f	f
173	TATIANA CRISTINA DA SILVA	ttattycsilva@gmail.com	\N	$2y$10$YWAClxxiyN0kFHeRubwPeOlkHL/jXqzq1TJucQ3r7vSOqmpqlRLxy	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	04322060609	interno	16	\N	10037	f	f
174	TATIANE FERREIRA LEAL	juridico@paraguacu.mg.gov.br	\N	$2y$10$0uwPM102pHKtr30Beby05OuUqLBNDrZwtRKepJA9WDjtsIy9JBbCS	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	13820479678	interno	16	\N	10044	f	f
175	THAIS MOURA VALADARES	thaismoura@conceitogestaopublica.com.br	\N	$2y$10$9S/0WmFNiUnBQrVJKRDfOeeYnutve3i0gakjG/AwJxFXUk7KdtZyy	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	10918321611	interno	16	\N	10101	f	f
176	TIAGO MATEUS ARAUJO	biohera@biohera.com.br	\N	$2y$10$E5CKk9d5orc5t/ZJseIBme0411p/IaeDh2eisRHNB50Kooz3CKybS	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	05154603621	interno	16	\N	10122	f	f
177	USUARIO TESTE	sistema2022@hotmail.com	\N	$2y$10$H5QJMi2W2lvTaeY60mU/WumC2wq0dn.hB4qbZooudceqjgDKvWmdK	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	\N	interno	16	\N	10051	f	f
178	VALDINEY PEREIRA DA SILVA	zeney1072@gmail.com	\N	$2y$10$hGs4SvjeCuRIUE3rkkjah.Ea52bKNQ7bV59J.dxEdNRlYSu6WugGm	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	83870008687	interno	16	\N	10105	f	f
179	VALERIA VIEIRA SIMOES	valeria@gmail.com	\N	$2y$10$4togQlXtmPdsN0DyF5leleNu6ioNeu/RxhtwrDv8uIbh0F5zUgnfy	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	09697077622	interno	16	\N	10034	f	f
180	VANESSA VIGATO TAVARES	vanessa.vigato.tavares@hotmail.com	\N	$2y$10$f3G9EMNNsRrmgxB5OpeWYuSHJCeyNxMjAcunE2C1960r8hlXwl0gG	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	13323815610	interno	16	\N	10036	f	f
181	VANUSA SANTOS MAIOLINI	prefeitura@prefeitura.com.br	\N	$2y$10$dBo8F1FKhXefkBahl6tjnO/7QzSmlV40kePQsuvs6ljrOmO6KVT7O	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	06266669617	interno	16	\N	10030	f	f
182	VINICIUS FERRAZ	viniciusferrazcoelho@gmail.com	\N	$2y$10$2A.T9YqE8VkPL/bMwmIMoeJwytPCdQLedaAq.gAdGSTfs4n/ProSu	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	13912474699	interno	16	\N	10002	f	f
183	VINICIUS RIBEIRO SILVA	cl301274@gmail.com	\N	$2y$10$ZkygFXw624pGXkIPieiVXelxWg0hT1r9LwTBlghgXth5DMT/39lsK	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	16526524699	interno	16	\N	10108	f	f
184	VITOR FERREIRA VAZ	vitorvaz001@gmail.com	\N	$2y$10$azHV.CXAtnqsMM6FarvAiOpib.ofgRDJagst.j6cmS60hHXo8MF52	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	02057622630	interno	16	\N	10056	f	f
191	Signatario 130...43	admconceitotec@gmail.com	\N	$2y$12$tUk8X2/7HvoSmyAb8Rlo/.9Gix7.pPCigAaRQVaSnQE5eWdxvVSxi	\N	2026-05-02 20:55:43	2026-05-02 20:55:43	13093688000143	externo	16	\N	\N	f	f
192	Signatario 536...34	majumoises@gmail.com	\N	$2y$12$e6.QkOEkydwKcOFit1IWXuoUwrarVPidDLi2pCRoo2D3Bnt5z9Aem	\N	2026-05-03 20:44:40	2026-05-03 20:44:40	53696751634	externo	16	\N	\N	f	f
95	Administrador	admin@ged.local	\N	$2y$12$mdybwLJm17To2Q0Acglzy.Zd9vb/COAkkHANYLCIQJluomVuBv2Um	\N	2026-04-30 18:02:23	2026-04-30 18:38:21	\N	interno	16	\N	\N	t	f
96	ADRIANO ALVES	vigilancia@paraguacu.com.br	\N	$2y$10$07y8QPBl9jMecDXwcoQ8Ye4INwIg1pYnbldg.uKVTfqdRxaSjRI9K	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	03506468642	interno	16	\N	10063	f	f
133	JUZÉ	foobaar@bar.com	\N	$2y$10$9kUcC2vU.fa8eAl22.j3BOkf9ZxlEyhJhueD5jxCNZyTDBs4V0HEC	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	53467060030	interno	17	305	10055	f	f
158	RAFAEL BRUNO GONÇALVES	rafaelbrunobiajo@gmail.com	\N	$2y$10$eWjgFQVrE96h/pEIPj8KEej7U.wVjvCrrSkuGZFqu0uE5o2POXbvG	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	09560763601	interno	17	305	10054	f	f
166	SAULO COSTA FERRAZ	saulocostaferraz@gmail.com	\N	$2y$10$iuz9fPYzGr8lOjsy923mQOj4cUgie3DUW8flw5i13qlRTgcNHOiEK	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	12078356689	interno	17	305	10033	f	f
97	ANDERSON MAZZEU JUNQUEIRA	mazzeusecretariasaude@gmail.com	\N	$2y$10$ICTbK/Q07nEPIwEvU1tByuVCCz07ZOag6NLxULNPndH0Zkyi9cNX.	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	05587901602	interno	16	\N	10028	f	f
98	ANDREIA DE MORAES PAIM	andreia_paim@hotmail.com	\N	$2y$10$kExwxnERrOvEtWmHlj5bluCj7MxMnrclGOeA7kUo8liuh9grzGmCa	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	71712976320	interno	16	\N	10078	f	f
99	BRUNO ALVES CAMPOS	compras@paraguacu.mg.gov.br	\N	$2y$10$nrUvtb4bKNziLyKuIXZ2O.lnKU9BQuc/jf/CFlqPgOJ/VEjz3Dr4S	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	08813336624	interno	16	\N	10009	f	f
100	BRUNO CLAUDINO DA SILVA	brunodasilva2294@gmail.com	\N	$2y$10$Qm0vL3w5jTl0IGGflBjNq.7wXsbR/5RE/B/4OMDMAsAzxmvGb1dRG	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	13106537639	interno	16	\N	10029	f	f
101	CARLOS ADRIANO SOUSA SILVA	carlos.dev@conceitogestaopublica.com.br	\N	$2y$10$pQDAvIBKUp0pSYZA.oEa9OS61z2tADIn9b9ooySY6AoC2BKevaMPG	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	11404593683	interno	16	\N	10085	f	f
102	CAROLINA VIEIRA FONSECA VASCONCELOS	carolrvv@hotmail.com	\N	$2y$10$tZlVMl/Ml.x4j5cs6QEvX.y79k/iZU2C7lCpLfhbWCPbRrVeFNrvW	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	12798457670	interno	16	\N	10013	f	f
103	CINTIA ALVES DE OLIVEIRA	cyntyanutry@hotmail.com	\N	$2y$10$zFzb7fev1aaUdHQYEaA7lOd6HRHhRRYSuq1bGXmLZi8ovplISdg6S	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	06079961610	interno	16	\N	10020	f	f
104	CLEIDE VIEIRA MARTINS	cleideviera41@hotmail.com	\N	$2y$10$k1w4WbnAdNTMLsxxhDc1I.5l/X4mcUKz13l6njFjAybJDoP/bHd8i	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	89724488187	interno	16	\N	10072	f	f
105	DEBHORA VELOSO	debhoraveloso@gmail.com	\N	$2y$10$fBOasgFCFJRfYrbogCvfn.c.EBnNLHv/fh0edflD5dYbK7MRcIBfa	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	08709471685	interno	16	\N	10097	f	f
106	DEBORA CRISTINA SANTOS	licitacao@paraguacu.mg.gov.br	\N	$2y$10$LdkuoHRpY2LWVkbeF7L04OOAfnG5jnAPpYAeJLw0AsI0z7K1oIHPO	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	06666492609	interno	16	\N	10010	f	f
107	DEBORA TAVARES DE CASTILHO CASSIMIRO	debora.castilho22@gmail.com	\N	$2y$10$RQPnTiXT0tN3dWzI6IdXW.JMGh1cIXABhHUR/qhTT8VQyATRQtOym	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	07638099699	interno	16	\N	10024	f	f
108	DINAH RIBEIRO DE OLIVEIRA PASSOS	dinahroliveira@hotmail.com	\N	$2y$10$4ebFziYWhvx/F4.GTIBrTeUcoDeRikLTcqZKFmxHpL/A4sibXUbGW	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	16031072661	interno	16	\N	10112	f	f
109	DIONE ALVES VIEIRA	dione-bada@hotmail.com	\N	$2y$10$OQQnr1onXoJQAIjJ2pPcm.fF8uzZuHymBXIgneGM2k8.vpxaT05va	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	09611089678	interno	16	\N	10006	f	f
110	DOMINGOS SAVIO CASTILHO	sedeea@paraguacu.mg.gov.br	\N	$2y$10$N/3ZJkqKCGxHyA3Zd68yMOETUqkFmrYAsdEUla.yeVOoCKRPzdRwO	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	44055706634	interno	16	\N	10119	f	f
111	DOUGLAS TAVARES	douglas.t_18@hotmail.com	\N	$2y$10$I1d1KiKwhn60ENQjR56GOeMYAD3tP.xZl04p0OtVzZPtCJIxjKfWu	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	12413383611	interno	16	\N	10019	f	f
112	ELIANE FERREIRA	elianeferreira06@yahoo.com.br	\N	$2y$10$SwuAg64F7E99zCt6w8IT/u2FlGNcVA/6hAxxUkGYwPeuKspI5Dgje	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	03878471629	interno	16	\N	10016	f	f
113	FERDINANDO SILVA	ferdinandosilva27@gmail.com	\N	$2y$10$OiPccsdAu6bLEyuNMacGSuln.saaoWScAH4xxOHZASfNMzXBiTWC2	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	10799386642	interno	16	\N	10040	f	f
114	GABRIEL FERREIRA GARCIA	gg6036044@gmail.com	\N	$2y$10$wlvVtkNX/bmjteVwD7jj9ul6wJ9aCMNyXnSw3bQBn5g/u9QD33m4W	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	02153305608	interno	16	\N	10048	f	f
115	GABRIEL JARDIM	gabrielj.empresarial@gmail.com	\N	$2y$10$naHNp2UeTb6CeMiQ2YTCFuHlWbEenewtCreeJqJeqymAUapxjBxUa	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	14030742670	interno	16	\N	10118	f	f
116	GABRIELA ESTEVANOVIC	gabrielaestevanovic@gmail.com	\N	$2y$10$uAaMn/.VtHZ6Q6ToUhmKC.KZD8up/lk4g9Ats6ut.IFnMrBb1yTxq	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	07230813603	interno	16	\N	10107	f	f
117	GIZELE QUINTINO COUTO NEIVA	gizeleqcn@hotmail.com	\N	$2y$10$MuCXXT46cQbhnEgcWsYtLOOzMMvM7IdH9hF.zrOe.UVOSio9LRxg6	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	11473373654	interno	16	\N	10058	f	f
185	VITORIA DE CARVALHO AZEVEDO	VITORIADECARVALHOAZEVEDO@GMAIL.COM	\N	$2y$10$v4OI15d6wdo37y/L8lUrr.M6WC0momfhvlbutdozOAPn6R3Npxz6O	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	11342271670	interno	16	\N	10025	f	f
186	VIVIAM CRISTINA COSTA SILVA SANTOS	vivianpref@hotmail.com	\N	$2y$10$QvQAfqnvlBzQAWOxQ88pReMK1RdJZ/56cJC8kQew77nJKB6Rtw8VG	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	04688046685	interno	16	\N	10043	f	f
187	VIVIANE DE SOUZA PEDRO SANTANA	prefeitura@paraguacu.com.br	\N	$2y$10$FUl.y4VnjcBIV7bdhgt4puqmW1JCbBkp7ZHnwPnPfwAbD/KhF4n/S	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	00753806681	interno	16	\N	10032	f	f
188	WALKER VALENTIM BISPO DE LIMA	bispo.walker@gmail.com	\N	$2y$10$Fu4hS/SW2u0RG9931A6/t.NrrL.MnkZe4DOBJ6Z6RRlSTsxmAeYYe	\N	2026-04-30 18:08:51	2026-04-30 18:08:51	11603778632	interno	16	\N	10098	f	f
\.


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: ged_assinaturas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_assinaturas_id_seq', 69, true);


--
-- Name: ged_audit_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_audit_logs_id_seq', 22, true);


--
-- Name: ged_buscas_salvas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_buscas_salvas_id_seq', 1, false);


--
-- Name: ged_certificados_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_certificados_id_seq', 1, true);


--
-- Name: ged_compartilhamentos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_compartilhamentos_id_seq', 1, false);


--
-- Name: ged_documentos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_documentos_id_seq', 47, true);


--
-- Name: ged_fluxo_etapas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_fluxo_etapas_id_seq', 1, false);


--
-- Name: ged_fluxo_instancias_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_fluxo_instancias_id_seq', 1, false);


--
-- Name: ged_fluxos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_fluxos_id_seq', 1, false);


--
-- Name: ged_metadados_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_metadados_id_seq', 2, true);


--
-- Name: ged_notificacoes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_notificacoes_id_seq', 25, true);


--
-- Name: ged_pastas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_pastas_id_seq', 5, true);


--
-- Name: ged_permissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_permissions_id_seq', 17, true);


--
-- Name: ged_roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_roles_id_seq', 4, true);


--
-- Name: ged_sistemas_integrados_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_sistemas_integrados_id_seq', 1, true);


--
-- Name: ged_solicitacoes_assinatura_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_solicitacoes_assinatura_id_seq', 43, true);


--
-- Name: ged_tags_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_tags_id_seq', 6, true);


--
-- Name: ged_tipos_documentais_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_tipos_documentais_id_seq', 33, true);


--
-- Name: ged_versoes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_versoes_id_seq', 43, true);


--
-- Name: ged_webhook_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_webhook_logs_id_seq', 18, true);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 30, true);


--
-- Name: portal_banners_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.portal_banners_id_seq', 4, true);


--
-- Name: portal_categorias_servicos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.portal_categorias_servicos_id_seq', 11, true);


--
-- Name: portal_cidadaos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.portal_cidadaos_id_seq', 1, true);


--
-- Name: portal_servicos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.portal_servicos_id_seq', 12, true);


--
-- Name: portal_solicitacao_eventos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.portal_solicitacao_eventos_id_seq', 6, true);


--
-- Name: portal_solicitacoes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.portal_solicitacoes_id_seq', 5, true);


--
-- Name: proc_anexos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proc_anexos_id_seq', 4, true);


--
-- Name: proc_circular_anexos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proc_circular_anexos_id_seq', 1, false);


--
-- Name: proc_circular_destinatarios_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proc_circular_destinatarios_id_seq', 1, false);


--
-- Name: proc_circulares_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proc_circulares_id_seq', 1, false);


--
-- Name: proc_comentarios_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proc_comentarios_id_seq', 1, false);


--
-- Name: proc_historico_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proc_historico_id_seq', 19, true);


--
-- Name: proc_memorando_anexos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proc_memorando_anexos_id_seq', 1, true);


--
-- Name: proc_memorando_destinatarios_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proc_memorando_destinatarios_id_seq', 1, true);


--
-- Name: proc_memorando_respostas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proc_memorando_respostas_id_seq', 4, true);


--
-- Name: proc_memorando_tramitacoes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proc_memorando_tramitacoes_id_seq', 1, true);


--
-- Name: proc_memorandos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proc_memorandos_id_seq', 1, true);


--
-- Name: proc_oficio_anexos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proc_oficio_anexos_id_seq', 1, false);


--
-- Name: proc_oficio_respostas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proc_oficio_respostas_id_seq', 1, false);


--
-- Name: proc_oficios_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proc_oficios_id_seq', 1, false);


--
-- Name: proc_processos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proc_processos_id_seq', 8, true);


--
-- Name: proc_tipo_etapas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proc_tipo_etapas_id_seq', 4, true);


--
-- Name: proc_tipos_processo_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proc_tipos_processo_id_seq', 3, true);


--
-- Name: proc_tramitacoes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proc_tramitacoes_id_seq', 12, true);


--
-- Name: ug_organograma_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ug_organograma_id_seq', 313, true);


--
-- Name: ugs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ugs_id_seq', 18, true);


--
-- Name: user_ugs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.user_ugs_id_seq', 10, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.users_id_seq', 192, true);


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
-- Name: ged_assinaturas ged_assinaturas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_assinaturas
    ADD CONSTRAINT ged_assinaturas_pkey PRIMARY KEY (id);


--
-- Name: ged_audit_logs ged_audit_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_audit_logs
    ADD CONSTRAINT ged_audit_logs_pkey PRIMARY KEY (id);


--
-- Name: ged_buscas_salvas ged_buscas_salvas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_buscas_salvas
    ADD CONSTRAINT ged_buscas_salvas_pkey PRIMARY KEY (id);


--
-- Name: ged_certificados ged_certificados_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_certificados
    ADD CONSTRAINT ged_certificados_pkey PRIMARY KEY (id);


--
-- Name: ged_certificados ged_certificados_user_id_thumbprint_sha256_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_certificados
    ADD CONSTRAINT ged_certificados_user_id_thumbprint_sha256_unique UNIQUE (user_id, thumbprint_sha256);


--
-- Name: ged_compartilhamentos ged_compartilhamentos_documento_id_usuario_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_compartilhamentos
    ADD CONSTRAINT ged_compartilhamentos_documento_id_usuario_id_unique UNIQUE (documento_id, usuario_id);


--
-- Name: ged_compartilhamentos ged_compartilhamentos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_compartilhamentos
    ADD CONSTRAINT ged_compartilhamentos_pkey PRIMARY KEY (id);


--
-- Name: ged_documento_tags ged_documento_tags_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_documento_tags
    ADD CONSTRAINT ged_documento_tags_pkey PRIMARY KEY (documento_id, tag_id);


--
-- Name: ged_documentos ged_documentos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_documentos
    ADD CONSTRAINT ged_documentos_pkey PRIMARY KEY (id);


--
-- Name: ged_documentos ged_documentos_qr_code_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_documentos
    ADD CONSTRAINT ged_documentos_qr_code_token_unique UNIQUE (qr_code_token);


--
-- Name: ged_favoritos ged_favoritos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_favoritos
    ADD CONSTRAINT ged_favoritos_pkey PRIMARY KEY (user_id, documento_id);


--
-- Name: ged_fluxo_etapas ged_fluxo_etapas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_fluxo_etapas
    ADD CONSTRAINT ged_fluxo_etapas_pkey PRIMARY KEY (id);


--
-- Name: ged_fluxo_instancias ged_fluxo_instancias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_fluxo_instancias
    ADD CONSTRAINT ged_fluxo_instancias_pkey PRIMARY KEY (id);


--
-- Name: ged_fluxos ged_fluxos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_fluxos
    ADD CONSTRAINT ged_fluxos_pkey PRIMARY KEY (id);


--
-- Name: ged_metadados ged_metadados_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_metadados
    ADD CONSTRAINT ged_metadados_pkey PRIMARY KEY (id);


--
-- Name: ged_notificacoes ged_notificacoes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_notificacoes
    ADD CONSTRAINT ged_notificacoes_pkey PRIMARY KEY (id);


--
-- Name: ged_pastas ged_pastas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_pastas
    ADD CONSTRAINT ged_pastas_pkey PRIMARY KEY (id);


--
-- Name: ged_permissions ged_permissions_nome_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_permissions
    ADD CONSTRAINT ged_permissions_nome_unique UNIQUE (nome);


--
-- Name: ged_permissions ged_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_permissions
    ADD CONSTRAINT ged_permissions_pkey PRIMARY KEY (id);


--
-- Name: ged_role_permissions ged_role_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_role_permissions
    ADD CONSTRAINT ged_role_permissions_pkey PRIMARY KEY (role_id, permission_id);


--
-- Name: ged_roles ged_roles_nome_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_roles
    ADD CONSTRAINT ged_roles_nome_unique UNIQUE (nome);


--
-- Name: ged_roles ged_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_roles
    ADD CONSTRAINT ged_roles_pkey PRIMARY KEY (id);


--
-- Name: ged_sistemas_integrados ged_sistemas_integrados_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_sistemas_integrados
    ADD CONSTRAINT ged_sistemas_integrados_codigo_unique UNIQUE (codigo);


--
-- Name: ged_sistemas_integrados ged_sistemas_integrados_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_sistemas_integrados
    ADD CONSTRAINT ged_sistemas_integrados_pkey PRIMARY KEY (id);


--
-- Name: ged_solicitacoes_assinatura ged_solicitacoes_assinatura_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_solicitacoes_assinatura
    ADD CONSTRAINT ged_solicitacoes_assinatura_pkey PRIMARY KEY (id);


--
-- Name: ged_tags ged_tags_nome_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_tags
    ADD CONSTRAINT ged_tags_nome_unique UNIQUE (nome);


--
-- Name: ged_tags ged_tags_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_tags
    ADD CONSTRAINT ged_tags_pkey PRIMARY KEY (id);


--
-- Name: ged_tipos_documentais ged_tipos_documentais_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_tipos_documentais
    ADD CONSTRAINT ged_tipos_documentais_pkey PRIMARY KEY (id);


--
-- Name: ged_user_roles ged_user_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_user_roles
    ADD CONSTRAINT ged_user_roles_pkey PRIMARY KEY (user_id, role_id);


--
-- Name: ged_versoes ged_versoes_documento_id_versao_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_versoes
    ADD CONSTRAINT ged_versoes_documento_id_versao_unique UNIQUE (documento_id, versao);


--
-- Name: ged_versoes ged_versoes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_versoes
    ADD CONSTRAINT ged_versoes_pkey PRIMARY KEY (id);


--
-- Name: ged_webhook_logs ged_webhook_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_webhook_logs
    ADD CONSTRAINT ged_webhook_logs_pkey PRIMARY KEY (id);


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
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: portal_banners portal_banners_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_banners
    ADD CONSTRAINT portal_banners_pkey PRIMARY KEY (id);


--
-- Name: portal_categorias_servicos portal_categorias_servicos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_categorias_servicos
    ADD CONSTRAINT portal_categorias_servicos_pkey PRIMARY KEY (id);


--
-- Name: portal_categorias_servicos portal_categorias_servicos_ug_id_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_categorias_servicos
    ADD CONSTRAINT portal_categorias_servicos_ug_id_slug_unique UNIQUE (ug_id, slug);


--
-- Name: portal_cidadaos portal_cidadaos_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_cidadaos
    ADD CONSTRAINT portal_cidadaos_email_unique UNIQUE (email);


--
-- Name: portal_cidadaos portal_cidadaos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_cidadaos
    ADD CONSTRAINT portal_cidadaos_pkey PRIMARY KEY (id);


--
-- Name: portal_servicos portal_servicos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_servicos
    ADD CONSTRAINT portal_servicos_pkey PRIMARY KEY (id);


--
-- Name: portal_servicos portal_servicos_ug_id_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_servicos
    ADD CONSTRAINT portal_servicos_ug_id_slug_unique UNIQUE (ug_id, slug);


--
-- Name: portal_solicitacao_eventos portal_solicitacao_eventos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_solicitacao_eventos
    ADD CONSTRAINT portal_solicitacao_eventos_pkey PRIMARY KEY (id);


--
-- Name: portal_solicitacoes portal_solicitacoes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_solicitacoes
    ADD CONSTRAINT portal_solicitacoes_pkey PRIMARY KEY (id);


--
-- Name: portal_solicitacoes portal_solicitacoes_ug_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_solicitacoes
    ADD CONSTRAINT portal_solicitacoes_ug_codigo_unique UNIQUE (ug_id, codigo);


--
-- Name: proc_anexos proc_anexos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_anexos
    ADD CONSTRAINT proc_anexos_pkey PRIMARY KEY (id);


--
-- Name: proc_circular_anexos proc_circular_anexos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_circular_anexos
    ADD CONSTRAINT proc_circular_anexos_pkey PRIMARY KEY (id);


--
-- Name: proc_circular_destinatarios proc_circular_destinatarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_circular_destinatarios
    ADD CONSTRAINT proc_circular_destinatarios_pkey PRIMARY KEY (id);


--
-- Name: proc_circulares proc_circulares_numero_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_circulares
    ADD CONSTRAINT proc_circulares_numero_unique UNIQUE (numero);


--
-- Name: proc_circulares proc_circulares_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_circulares
    ADD CONSTRAINT proc_circulares_pkey PRIMARY KEY (id);


--
-- Name: proc_circulares proc_circulares_qr_code_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_circulares
    ADD CONSTRAINT proc_circulares_qr_code_token_unique UNIQUE (qr_code_token);


--
-- Name: proc_comentarios proc_comentarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_comentarios
    ADD CONSTRAINT proc_comentarios_pkey PRIMARY KEY (id);


--
-- Name: proc_historico proc_historico_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_historico
    ADD CONSTRAINT proc_historico_pkey PRIMARY KEY (id);


--
-- Name: proc_memorando_anexos proc_memorando_anexos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_anexos
    ADD CONSTRAINT proc_memorando_anexos_pkey PRIMARY KEY (id);


--
-- Name: proc_memorando_destinatarios proc_memorando_destinatarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_destinatarios
    ADD CONSTRAINT proc_memorando_destinatarios_pkey PRIMARY KEY (id);


--
-- Name: proc_memorando_respostas proc_memorando_respostas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_respostas
    ADD CONSTRAINT proc_memorando_respostas_pkey PRIMARY KEY (id);


--
-- Name: proc_memorando_tramitacoes proc_memorando_tramitacoes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_tramitacoes
    ADD CONSTRAINT proc_memorando_tramitacoes_pkey PRIMARY KEY (id);


--
-- Name: proc_memorandos proc_memorandos_numero_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorandos
    ADD CONSTRAINT proc_memorandos_numero_unique UNIQUE (numero);


--
-- Name: proc_memorandos proc_memorandos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorandos
    ADD CONSTRAINT proc_memorandos_pkey PRIMARY KEY (id);


--
-- Name: proc_memorandos proc_memorandos_qr_code_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorandos
    ADD CONSTRAINT proc_memorandos_qr_code_token_unique UNIQUE (qr_code_token);


--
-- Name: proc_oficio_anexos proc_oficio_anexos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_oficio_anexos
    ADD CONSTRAINT proc_oficio_anexos_pkey PRIMARY KEY (id);


--
-- Name: proc_oficio_respostas proc_oficio_respostas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_oficio_respostas
    ADD CONSTRAINT proc_oficio_respostas_pkey PRIMARY KEY (id);


--
-- Name: proc_oficios proc_oficios_numero_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_oficios
    ADD CONSTRAINT proc_oficios_numero_unique UNIQUE (numero);


--
-- Name: proc_oficios proc_oficios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_oficios
    ADD CONSTRAINT proc_oficios_pkey PRIMARY KEY (id);


--
-- Name: proc_oficios proc_oficios_qr_code_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_oficios
    ADD CONSTRAINT proc_oficios_qr_code_token_unique UNIQUE (qr_code_token);


--
-- Name: proc_oficios proc_oficios_rastreio_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_oficios
    ADD CONSTRAINT proc_oficios_rastreio_token_unique UNIQUE (rastreio_token);


--
-- Name: proc_processos proc_processos_numero_protocolo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_processos
    ADD CONSTRAINT proc_processos_numero_protocolo_unique UNIQUE (numero_protocolo);


--
-- Name: proc_processos proc_processos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_processos
    ADD CONSTRAINT proc_processos_pkey PRIMARY KEY (id);


--
-- Name: proc_tipo_etapas proc_tipo_etapas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_tipo_etapas
    ADD CONSTRAINT proc_tipo_etapas_pkey PRIMARY KEY (id);


--
-- Name: proc_tipos_processo proc_tipos_processo_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_tipos_processo
    ADD CONSTRAINT proc_tipos_processo_pkey PRIMARY KEY (id);


--
-- Name: proc_tramitacoes proc_tramitacoes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_tramitacoes
    ADD CONSTRAINT proc_tramitacoes_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: ug_organograma ug_organograma_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ug_organograma
    ADD CONSTRAINT ug_organograma_pkey PRIMARY KEY (id);


--
-- Name: ugs ugs_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ugs
    ADD CONSTRAINT ugs_codigo_unique UNIQUE (codigo);


--
-- Name: ugs ugs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ugs
    ADD CONSTRAINT ugs_pkey PRIMARY KEY (id);


--
-- Name: ugs ugs_portal_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ugs
    ADD CONSTRAINT ugs_portal_slug_unique UNIQUE (portal_slug);


--
-- Name: user_ugs user_ugs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_ugs
    ADD CONSTRAINT user_ugs_pkey PRIMARY KEY (id);


--
-- Name: user_ugs user_ugs_user_id_ug_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_ugs
    ADD CONSTRAINT user_ugs_user_id_ug_id_unique UNIQUE (user_id, ug_id);


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
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: ged_certificados_subject_cpf_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ged_certificados_subject_cpf_index ON public.ged_certificados USING btree (subject_cpf);


--
-- Name: ged_documentos_autor_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ged_documentos_autor_id_index ON public.ged_documentos USING btree (autor_id);


--
-- Name: ged_documentos_pasta_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ged_documentos_pasta_id_index ON public.ged_documentos USING btree (pasta_id);


--
-- Name: ged_documentos_sistema_origem_numero_externo_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ged_documentos_sistema_origem_numero_externo_index ON public.ged_documentos USING btree (sistema_origem, numero_externo);


--
-- Name: ged_documentos_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ged_documentos_status_index ON public.ged_documentos USING btree (status);


--
-- Name: ged_documentos_tipo_documental_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ged_documentos_tipo_documental_id_index ON public.ged_documentos USING btree (tipo_documental_id);


--
-- Name: ged_documentos_ug_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ged_documentos_ug_id_index ON public.ged_documentos USING btree (ug_id);


--
-- Name: ged_metadados_documento_id_chave_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ged_metadados_documento_id_chave_index ON public.ged_metadados USING btree (documento_id, chave);


--
-- Name: ged_pastas_parent_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ged_pastas_parent_id_index ON public.ged_pastas USING btree (parent_id);


--
-- Name: ged_pastas_path_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ged_pastas_path_index ON public.ged_pastas USING btree (path);


--
-- Name: ged_pastas_ug_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ged_pastas_ug_id_index ON public.ged_pastas USING btree (ug_id);


--
-- Name: ged_webhook_logs_evento_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ged_webhook_logs_evento_index ON public.ged_webhook_logs USING btree (evento);


--
-- Name: ged_webhook_logs_sistema_origem_documento_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ged_webhook_logs_sistema_origem_documento_id_index ON public.ged_webhook_logs USING btree (sistema_origem, documento_id);


--
-- Name: ged_webhook_logs_sucesso_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ged_webhook_logs_sucesso_index ON public.ged_webhook_logs USING btree (sucesso);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: portal_banners_ug_id_ordem_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX portal_banners_ug_id_ordem_index ON public.portal_banners USING btree (ug_id, ordem);


--
-- Name: portal_categorias_servicos_ug_id_ativo_ordem_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX portal_categorias_servicos_ug_id_ativo_ordem_index ON public.portal_categorias_servicos USING btree (ug_id, ativo, ordem);


--
-- Name: portal_cidadaos_cpf_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX portal_cidadaos_cpf_index ON public.portal_cidadaos USING btree (cpf);


--
-- Name: portal_servicos_ug_id_publicado_categoria_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX portal_servicos_ug_id_publicado_categoria_id_index ON public.portal_servicos USING btree (ug_id, publicado, categoria_id);


--
-- Name: portal_servicos_ug_id_publicado_publico_alvo_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX portal_servicos_ug_id_publicado_publico_alvo_index ON public.portal_servicos USING btree (ug_id, publicado, publico_alvo);


--
-- Name: portal_solicitacao_eventos_solicitacao_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX portal_solicitacao_eventos_solicitacao_id_index ON public.portal_solicitacao_eventos USING btree (solicitacao_id);


--
-- Name: portal_solicitacoes_cidadao_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX portal_solicitacoes_cidadao_id_status_index ON public.portal_solicitacoes USING btree (cidadao_id, status);


--
-- Name: portal_solicitacoes_processo_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX portal_solicitacoes_processo_id_index ON public.portal_solicitacoes USING btree (processo_id);


--
-- Name: portal_solicitacoes_ug_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX portal_solicitacoes_ug_id_status_index ON public.portal_solicitacoes USING btree (ug_id, status);


--
-- Name: proc_anexos_processo_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_anexos_processo_id_index ON public.proc_anexos USING btree (processo_id);


--
-- Name: proc_circular_anexos_circular_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_circular_anexos_circular_id_index ON public.proc_circular_anexos USING btree (circular_id);


--
-- Name: proc_circular_destinatarios_circular_id_usuario_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_circular_destinatarios_circular_id_usuario_id_index ON public.proc_circular_destinatarios USING btree (circular_id, usuario_id);


--
-- Name: proc_circulares_remetente_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_circulares_remetente_id_index ON public.proc_circulares USING btree (remetente_id);


--
-- Name: proc_circulares_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_circulares_status_index ON public.proc_circulares USING btree (status);


--
-- Name: proc_circulares_ug_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_circulares_ug_id_index ON public.proc_circulares USING btree (ug_id);


--
-- Name: proc_comentarios_processo_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_comentarios_processo_id_index ON public.proc_comentarios USING btree (processo_id);


--
-- Name: proc_historico_processo_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_historico_processo_id_created_at_index ON public.proc_historico USING btree (processo_id, created_at);


--
-- Name: proc_memorando_anexos_memorando_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_memorando_anexos_memorando_id_index ON public.proc_memorando_anexos USING btree (memorando_id);


--
-- Name: proc_memorando_destinatarios_memorando_id_usuario_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_memorando_destinatarios_memorando_id_usuario_id_index ON public.proc_memorando_destinatarios USING btree (memorando_id, usuario_id);


--
-- Name: proc_memorando_respostas_memorando_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_memorando_respostas_memorando_id_index ON public.proc_memorando_respostas USING btree (memorando_id);


--
-- Name: proc_memorando_tramitacoes_destino_unidade_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_memorando_tramitacoes_destino_unidade_id_index ON public.proc_memorando_tramitacoes USING btree (destino_unidade_id);


--
-- Name: proc_memorando_tramitacoes_destino_usuario_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_memorando_tramitacoes_destino_usuario_id_index ON public.proc_memorando_tramitacoes USING btree (destino_usuario_id);


--
-- Name: proc_memorando_tramitacoes_memorando_id_em_uso_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_memorando_tramitacoes_memorando_id_em_uso_index ON public.proc_memorando_tramitacoes USING btree (memorando_id, em_uso);


--
-- Name: proc_memorandos_remetente_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_memorandos_remetente_id_index ON public.proc_memorandos USING btree (remetente_id);


--
-- Name: proc_memorandos_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_memorandos_status_index ON public.proc_memorandos USING btree (status);


--
-- Name: proc_memorandos_ug_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_memorandos_ug_id_index ON public.proc_memorandos USING btree (ug_id);


--
-- Name: proc_oficio_anexos_oficio_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_oficio_anexos_oficio_id_index ON public.proc_oficio_anexos USING btree (oficio_id);


--
-- Name: proc_oficio_respostas_oficio_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_oficio_respostas_oficio_id_index ON public.proc_oficio_respostas USING btree (oficio_id);


--
-- Name: proc_oficios_remetente_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_oficios_remetente_id_index ON public.proc_oficios USING btree (remetente_id);


--
-- Name: proc_oficios_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_oficios_status_index ON public.proc_oficios USING btree (status);


--
-- Name: proc_oficios_ug_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_oficios_ug_id_index ON public.proc_oficios USING btree (ug_id);


--
-- Name: proc_processos_aberto_por_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_processos_aberto_por_index ON public.proc_processos USING btree (aberto_por);


--
-- Name: proc_processos_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_processos_status_index ON public.proc_processos USING btree (status);


--
-- Name: proc_processos_tipo_processo_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_processos_tipo_processo_id_index ON public.proc_processos USING btree (tipo_processo_id);


--
-- Name: proc_processos_ug_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_processos_ug_id_index ON public.proc_processos USING btree (ug_id);


--
-- Name: proc_tipo_etapas_tipo_processo_id_ordem_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_tipo_etapas_tipo_processo_id_ordem_index ON public.proc_tipo_etapas USING btree (tipo_processo_id, ordem);


--
-- Name: proc_tramitacoes_destinatario_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_tramitacoes_destinatario_id_status_index ON public.proc_tramitacoes USING btree (destinatario_id, status);


--
-- Name: proc_tramitacoes_prazo_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_tramitacoes_prazo_index ON public.proc_tramitacoes USING btree (prazo);


--
-- Name: proc_tramitacoes_processo_id_ordem_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proc_tramitacoes_processo_id_ordem_index ON public.proc_tramitacoes USING btree (processo_id, ordem);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: ug_organograma_legado_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ug_organograma_legado_id_index ON public.ug_organograma USING btree (legado_id);


--
-- Name: ug_organograma_ug_id_nivel_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ug_organograma_ug_id_nivel_index ON public.ug_organograma USING btree (ug_id, nivel);


--
-- Name: ug_organograma_ug_id_parent_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ug_organograma_ug_id_parent_id_index ON public.ug_organograma USING btree (ug_id, parent_id);


--
-- Name: ugs_ativo_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ugs_ativo_index ON public.ugs USING btree (ativo);


--
-- Name: ugs_legado_orgao_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ugs_legado_orgao_id_index ON public.ugs USING btree (legado_orgao_id);


--
-- Name: user_ugs_ug_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_ugs_ug_id_index ON public.user_ugs USING btree (ug_id);


--
-- Name: users_legado_usuario_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_legado_usuario_id_index ON public.users USING btree (legado_usuario_id);


--
-- Name: users_tipo_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_tipo_index ON public.users USING btree (tipo);


--
-- Name: ged_assinaturas ged_assinaturas_certificado_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_assinaturas
    ADD CONSTRAINT ged_assinaturas_certificado_id_foreign FOREIGN KEY (certificado_id) REFERENCES public.ged_certificados(id) ON DELETE SET NULL;


--
-- Name: ged_assinaturas ged_assinaturas_documento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_assinaturas
    ADD CONSTRAINT ged_assinaturas_documento_id_foreign FOREIGN KEY (documento_id) REFERENCES public.ged_documentos(id) ON DELETE CASCADE;


--
-- Name: ged_assinaturas ged_assinaturas_signatario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_assinaturas
    ADD CONSTRAINT ged_assinaturas_signatario_id_foreign FOREIGN KEY (signatario_id) REFERENCES public.users(id);


--
-- Name: ged_assinaturas ged_assinaturas_solicitacao_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_assinaturas
    ADD CONSTRAINT ged_assinaturas_solicitacao_id_foreign FOREIGN KEY (solicitacao_id) REFERENCES public.ged_solicitacoes_assinatura(id) ON DELETE CASCADE;


--
-- Name: ged_assinaturas ged_assinaturas_versao_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_assinaturas
    ADD CONSTRAINT ged_assinaturas_versao_id_foreign FOREIGN KEY (versao_id) REFERENCES public.ged_versoes(id) ON DELETE SET NULL;


--
-- Name: ged_audit_logs ged_audit_logs_documento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_audit_logs
    ADD CONSTRAINT ged_audit_logs_documento_id_foreign FOREIGN KEY (documento_id) REFERENCES public.ged_documentos(id) ON DELETE SET NULL;


--
-- Name: ged_audit_logs ged_audit_logs_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_audit_logs
    ADD CONSTRAINT ged_audit_logs_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: ged_buscas_salvas ged_buscas_salvas_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_buscas_salvas
    ADD CONSTRAINT ged_buscas_salvas_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: ged_certificados ged_certificados_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_certificados
    ADD CONSTRAINT ged_certificados_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: ged_compartilhamentos ged_compartilhamentos_criado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_compartilhamentos
    ADD CONSTRAINT ged_compartilhamentos_criado_por_foreign FOREIGN KEY (criado_por) REFERENCES public.users(id);


--
-- Name: ged_compartilhamentos ged_compartilhamentos_documento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_compartilhamentos
    ADD CONSTRAINT ged_compartilhamentos_documento_id_foreign FOREIGN KEY (documento_id) REFERENCES public.ged_documentos(id) ON DELETE CASCADE;


--
-- Name: ged_compartilhamentos ged_compartilhamentos_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_compartilhamentos
    ADD CONSTRAINT ged_compartilhamentos_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: ged_documento_tags ged_documento_tags_documento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_documento_tags
    ADD CONSTRAINT ged_documento_tags_documento_id_foreign FOREIGN KEY (documento_id) REFERENCES public.ged_documentos(id) ON DELETE CASCADE;


--
-- Name: ged_documento_tags ged_documento_tags_tag_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_documento_tags
    ADD CONSTRAINT ged_documento_tags_tag_id_foreign FOREIGN KEY (tag_id) REFERENCES public.ged_tags(id) ON DELETE CASCADE;


--
-- Name: ged_documentos ged_documentos_autor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_documentos
    ADD CONSTRAINT ged_documentos_autor_id_foreign FOREIGN KEY (autor_id) REFERENCES public.users(id);


--
-- Name: ged_documentos ged_documentos_check_out_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_documentos
    ADD CONSTRAINT ged_documentos_check_out_por_foreign FOREIGN KEY (check_out_por) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: ged_documentos ged_documentos_pasta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_documentos
    ADD CONSTRAINT ged_documentos_pasta_id_foreign FOREIGN KEY (pasta_id) REFERENCES public.ged_pastas(id) ON DELETE SET NULL;


--
-- Name: ged_documentos ged_documentos_tipo_documental_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_documentos
    ADD CONSTRAINT ged_documentos_tipo_documental_id_foreign FOREIGN KEY (tipo_documental_id) REFERENCES public.ged_tipos_documentais(id) ON DELETE SET NULL;


--
-- Name: ged_documentos ged_documentos_ug_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_documentos
    ADD CONSTRAINT ged_documentos_ug_id_foreign FOREIGN KEY (ug_id) REFERENCES public.ugs(id) ON DELETE SET NULL;


--
-- Name: ged_favoritos ged_favoritos_documento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_favoritos
    ADD CONSTRAINT ged_favoritos_documento_id_foreign FOREIGN KEY (documento_id) REFERENCES public.ged_documentos(id) ON DELETE CASCADE;


--
-- Name: ged_favoritos ged_favoritos_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_favoritos
    ADD CONSTRAINT ged_favoritos_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: ged_fluxo_etapas ged_fluxo_etapas_instancia_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_fluxo_etapas
    ADD CONSTRAINT ged_fluxo_etapas_instancia_id_foreign FOREIGN KEY (instancia_id) REFERENCES public.ged_fluxo_instancias(id) ON DELETE CASCADE;


--
-- Name: ged_fluxo_etapas ged_fluxo_etapas_responsavel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_fluxo_etapas
    ADD CONSTRAINT ged_fluxo_etapas_responsavel_id_foreign FOREIGN KEY (responsavel_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: ged_fluxo_instancias ged_fluxo_instancias_documento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_fluxo_instancias
    ADD CONSTRAINT ged_fluxo_instancias_documento_id_foreign FOREIGN KEY (documento_id) REFERENCES public.ged_documentos(id);


--
-- Name: ged_fluxo_instancias ged_fluxo_instancias_fluxo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_fluxo_instancias
    ADD CONSTRAINT ged_fluxo_instancias_fluxo_id_foreign FOREIGN KEY (fluxo_id) REFERENCES public.ged_fluxos(id);


--
-- Name: ged_fluxo_instancias ged_fluxo_instancias_iniciado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_fluxo_instancias
    ADD CONSTRAINT ged_fluxo_instancias_iniciado_por_foreign FOREIGN KEY (iniciado_por) REFERENCES public.users(id);


--
-- Name: ged_fluxos ged_fluxos_criado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_fluxos
    ADD CONSTRAINT ged_fluxos_criado_por_foreign FOREIGN KEY (criado_por) REFERENCES public.users(id);


--
-- Name: ged_metadados ged_metadados_documento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_metadados
    ADD CONSTRAINT ged_metadados_documento_id_foreign FOREIGN KEY (documento_id) REFERENCES public.ged_documentos(id) ON DELETE CASCADE;


--
-- Name: ged_notificacoes ged_notificacoes_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_notificacoes
    ADD CONSTRAINT ged_notificacoes_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: ged_pastas ged_pastas_criado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_pastas
    ADD CONSTRAINT ged_pastas_criado_por_foreign FOREIGN KEY (criado_por) REFERENCES public.users(id);


--
-- Name: ged_pastas ged_pastas_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_pastas
    ADD CONSTRAINT ged_pastas_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.ged_pastas(id) ON DELETE SET NULL;


--
-- Name: ged_pastas ged_pastas_ug_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_pastas
    ADD CONSTRAINT ged_pastas_ug_id_foreign FOREIGN KEY (ug_id) REFERENCES public.ugs(id) ON DELETE SET NULL;


--
-- Name: ged_role_permissions ged_role_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_role_permissions
    ADD CONSTRAINT ged_role_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.ged_permissions(id) ON DELETE CASCADE;


--
-- Name: ged_role_permissions ged_role_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_role_permissions
    ADD CONSTRAINT ged_role_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.ged_roles(id) ON DELETE CASCADE;


--
-- Name: ged_solicitacoes_assinatura ged_solicitacoes_assinatura_documento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_solicitacoes_assinatura
    ADD CONSTRAINT ged_solicitacoes_assinatura_documento_id_foreign FOREIGN KEY (documento_id) REFERENCES public.ged_documentos(id) ON DELETE CASCADE;


--
-- Name: ged_solicitacoes_assinatura ged_solicitacoes_assinatura_solicitante_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_solicitacoes_assinatura
    ADD CONSTRAINT ged_solicitacoes_assinatura_solicitante_id_foreign FOREIGN KEY (solicitante_id) REFERENCES public.users(id);


--
-- Name: ged_user_roles ged_user_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_user_roles
    ADD CONSTRAINT ged_user_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.ged_roles(id) ON DELETE CASCADE;


--
-- Name: ged_user_roles ged_user_roles_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_user_roles
    ADD CONSTRAINT ged_user_roles_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: ged_versoes ged_versoes_autor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_versoes
    ADD CONSTRAINT ged_versoes_autor_id_foreign FOREIGN KEY (autor_id) REFERENCES public.users(id);


--
-- Name: ged_versoes ged_versoes_documento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_versoes
    ADD CONSTRAINT ged_versoes_documento_id_foreign FOREIGN KEY (documento_id) REFERENCES public.ged_documentos(id) ON DELETE CASCADE;


--
-- Name: ged_webhook_logs ged_webhook_logs_documento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ged_webhook_logs
    ADD CONSTRAINT ged_webhook_logs_documento_id_foreign FOREIGN KEY (documento_id) REFERENCES public.ged_documentos(id) ON DELETE CASCADE;


--
-- Name: portal_banners portal_banners_ug_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_banners
    ADD CONSTRAINT portal_banners_ug_id_foreign FOREIGN KEY (ug_id) REFERENCES public.ugs(id) ON DELETE CASCADE;


--
-- Name: portal_categorias_servicos portal_categorias_servicos_ug_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_categorias_servicos
    ADD CONSTRAINT portal_categorias_servicos_ug_id_foreign FOREIGN KEY (ug_id) REFERENCES public.ugs(id) ON DELETE CASCADE;


--
-- Name: portal_servicos portal_servicos_categoria_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_servicos
    ADD CONSTRAINT portal_servicos_categoria_id_foreign FOREIGN KEY (categoria_id) REFERENCES public.portal_categorias_servicos(id) ON DELETE SET NULL;


--
-- Name: portal_servicos portal_servicos_setor_responsavel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_servicos
    ADD CONSTRAINT portal_servicos_setor_responsavel_id_foreign FOREIGN KEY (setor_responsavel_id) REFERENCES public.ug_organograma(id) ON DELETE SET NULL;


--
-- Name: portal_servicos portal_servicos_tipo_processo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_servicos
    ADD CONSTRAINT portal_servicos_tipo_processo_id_foreign FOREIGN KEY (tipo_processo_id) REFERENCES public.proc_tipos_processo(id) ON DELETE SET NULL;


--
-- Name: portal_servicos portal_servicos_ug_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_servicos
    ADD CONSTRAINT portal_servicos_ug_id_foreign FOREIGN KEY (ug_id) REFERENCES public.ugs(id) ON DELETE CASCADE;


--
-- Name: portal_solicitacao_eventos portal_solicitacao_eventos_autor_cidadao_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_solicitacao_eventos
    ADD CONSTRAINT portal_solicitacao_eventos_autor_cidadao_id_foreign FOREIGN KEY (autor_cidadao_id) REFERENCES public.portal_cidadaos(id) ON DELETE SET NULL;


--
-- Name: portal_solicitacao_eventos portal_solicitacao_eventos_autor_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_solicitacao_eventos
    ADD CONSTRAINT portal_solicitacao_eventos_autor_user_id_foreign FOREIGN KEY (autor_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: portal_solicitacao_eventos portal_solicitacao_eventos_solicitacao_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_solicitacao_eventos
    ADD CONSTRAINT portal_solicitacao_eventos_solicitacao_id_foreign FOREIGN KEY (solicitacao_id) REFERENCES public.portal_solicitacoes(id) ON DELETE CASCADE;


--
-- Name: portal_solicitacoes portal_solicitacoes_atendente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_solicitacoes
    ADD CONSTRAINT portal_solicitacoes_atendente_id_foreign FOREIGN KEY (atendente_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: portal_solicitacoes portal_solicitacoes_cidadao_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_solicitacoes
    ADD CONSTRAINT portal_solicitacoes_cidadao_id_foreign FOREIGN KEY (cidadao_id) REFERENCES public.portal_cidadaos(id) ON DELETE CASCADE;


--
-- Name: portal_solicitacoes portal_solicitacoes_processo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_solicitacoes
    ADD CONSTRAINT portal_solicitacoes_processo_id_foreign FOREIGN KEY (processo_id) REFERENCES public.proc_processos(id) ON DELETE SET NULL;


--
-- Name: portal_solicitacoes portal_solicitacoes_servico_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_solicitacoes
    ADD CONSTRAINT portal_solicitacoes_servico_id_foreign FOREIGN KEY (servico_id) REFERENCES public.portal_servicos(id) ON DELETE CASCADE;


--
-- Name: portal_solicitacoes portal_solicitacoes_ug_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_solicitacoes
    ADD CONSTRAINT portal_solicitacoes_ug_id_foreign FOREIGN KEY (ug_id) REFERENCES public.ugs(id) ON DELETE CASCADE;


--
-- Name: proc_anexos proc_anexos_enviado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_anexos
    ADD CONSTRAINT proc_anexos_enviado_por_foreign FOREIGN KEY (enviado_por) REFERENCES public.users(id);


--
-- Name: proc_anexos proc_anexos_processo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_anexos
    ADD CONSTRAINT proc_anexos_processo_id_foreign FOREIGN KEY (processo_id) REFERENCES public.proc_processos(id) ON DELETE CASCADE;


--
-- Name: proc_anexos proc_anexos_tramitacao_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_anexos
    ADD CONSTRAINT proc_anexos_tramitacao_id_foreign FOREIGN KEY (tramitacao_id) REFERENCES public.proc_tramitacoes(id) ON DELETE CASCADE;


--
-- Name: proc_circular_anexos proc_circular_anexos_circular_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_circular_anexos
    ADD CONSTRAINT proc_circular_anexos_circular_id_foreign FOREIGN KEY (circular_id) REFERENCES public.proc_circulares(id) ON DELETE CASCADE;


--
-- Name: proc_circular_anexos proc_circular_anexos_enviado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_circular_anexos
    ADD CONSTRAINT proc_circular_anexos_enviado_por_foreign FOREIGN KEY (enviado_por) REFERENCES public.users(id);


--
-- Name: proc_circular_destinatarios proc_circular_destinatarios_circular_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_circular_destinatarios
    ADD CONSTRAINT proc_circular_destinatarios_circular_id_foreign FOREIGN KEY (circular_id) REFERENCES public.proc_circulares(id) ON DELETE CASCADE;


--
-- Name: proc_circular_destinatarios proc_circular_destinatarios_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_circular_destinatarios
    ADD CONSTRAINT proc_circular_destinatarios_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: proc_circulares proc_circulares_remetente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_circulares
    ADD CONSTRAINT proc_circulares_remetente_id_foreign FOREIGN KEY (remetente_id) REFERENCES public.users(id);


--
-- Name: proc_circulares proc_circulares_ug_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_circulares
    ADD CONSTRAINT proc_circulares_ug_id_foreign FOREIGN KEY (ug_id) REFERENCES public.ugs(id) ON DELETE SET NULL;


--
-- Name: proc_comentarios proc_comentarios_processo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_comentarios
    ADD CONSTRAINT proc_comentarios_processo_id_foreign FOREIGN KEY (processo_id) REFERENCES public.proc_processos(id) ON DELETE CASCADE;


--
-- Name: proc_comentarios proc_comentarios_tramitacao_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_comentarios
    ADD CONSTRAINT proc_comentarios_tramitacao_id_foreign FOREIGN KEY (tramitacao_id) REFERENCES public.proc_tramitacoes(id) ON DELETE CASCADE;


--
-- Name: proc_comentarios proc_comentarios_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_comentarios
    ADD CONSTRAINT proc_comentarios_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.users(id);


--
-- Name: proc_historico proc_historico_processo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_historico
    ADD CONSTRAINT proc_historico_processo_id_foreign FOREIGN KEY (processo_id) REFERENCES public.proc_processos(id) ON DELETE CASCADE;


--
-- Name: proc_historico proc_historico_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_historico
    ADD CONSTRAINT proc_historico_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: proc_memorando_anexos proc_memorando_anexos_enviado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_anexos
    ADD CONSTRAINT proc_memorando_anexos_enviado_por_foreign FOREIGN KEY (enviado_por) REFERENCES public.users(id);


--
-- Name: proc_memorando_anexos proc_memorando_anexos_memorando_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_anexos
    ADD CONSTRAINT proc_memorando_anexos_memorando_id_foreign FOREIGN KEY (memorando_id) REFERENCES public.proc_memorandos(id) ON DELETE CASCADE;


--
-- Name: proc_memorando_destinatarios proc_memorando_destinatarios_memorando_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_destinatarios
    ADD CONSTRAINT proc_memorando_destinatarios_memorando_id_foreign FOREIGN KEY (memorando_id) REFERENCES public.proc_memorandos(id) ON DELETE CASCADE;


--
-- Name: proc_memorando_destinatarios proc_memorando_destinatarios_unidade_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_destinatarios
    ADD CONSTRAINT proc_memorando_destinatarios_unidade_id_foreign FOREIGN KEY (unidade_id) REFERENCES public.ug_organograma(id) ON DELETE SET NULL;


--
-- Name: proc_memorando_destinatarios proc_memorando_destinatarios_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_destinatarios
    ADD CONSTRAINT proc_memorando_destinatarios_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: proc_memorando_respostas proc_memorando_respostas_memorando_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_respostas
    ADD CONSTRAINT proc_memorando_respostas_memorando_id_foreign FOREIGN KEY (memorando_id) REFERENCES public.proc_memorandos(id) ON DELETE CASCADE;


--
-- Name: proc_memorando_respostas proc_memorando_respostas_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_respostas
    ADD CONSTRAINT proc_memorando_respostas_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.users(id);


--
-- Name: proc_memorando_tramitacoes proc_memorando_tramitacoes_destino_unidade_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_tramitacoes
    ADD CONSTRAINT proc_memorando_tramitacoes_destino_unidade_id_foreign FOREIGN KEY (destino_unidade_id) REFERENCES public.ug_organograma(id) ON DELETE SET NULL;


--
-- Name: proc_memorando_tramitacoes proc_memorando_tramitacoes_destino_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_tramitacoes
    ADD CONSTRAINT proc_memorando_tramitacoes_destino_usuario_id_foreign FOREIGN KEY (destino_usuario_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: proc_memorando_tramitacoes proc_memorando_tramitacoes_memorando_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_tramitacoes
    ADD CONSTRAINT proc_memorando_tramitacoes_memorando_id_foreign FOREIGN KEY (memorando_id) REFERENCES public.proc_memorandos(id) ON DELETE CASCADE;


--
-- Name: proc_memorando_tramitacoes proc_memorando_tramitacoes_origem_unidade_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_tramitacoes
    ADD CONSTRAINT proc_memorando_tramitacoes_origem_unidade_id_foreign FOREIGN KEY (origem_unidade_id) REFERENCES public.ug_organograma(id) ON DELETE SET NULL;


--
-- Name: proc_memorando_tramitacoes proc_memorando_tramitacoes_origem_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_tramitacoes
    ADD CONSTRAINT proc_memorando_tramitacoes_origem_usuario_id_foreign FOREIGN KEY (origem_usuario_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: proc_memorando_tramitacoes proc_memorando_tramitacoes_tramite_origem_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorando_tramitacoes
    ADD CONSTRAINT proc_memorando_tramitacoes_tramite_origem_id_foreign FOREIGN KEY (tramite_origem_id) REFERENCES public.proc_memorando_tramitacoes(id) ON DELETE SET NULL;


--
-- Name: proc_memorandos proc_memorandos_remetente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorandos
    ADD CONSTRAINT proc_memorandos_remetente_id_foreign FOREIGN KEY (remetente_id) REFERENCES public.users(id);


--
-- Name: proc_memorandos proc_memorandos_ug_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_memorandos
    ADD CONSTRAINT proc_memorandos_ug_id_foreign FOREIGN KEY (ug_id) REFERENCES public.ugs(id) ON DELETE SET NULL;


--
-- Name: proc_oficio_anexos proc_oficio_anexos_enviado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_oficio_anexos
    ADD CONSTRAINT proc_oficio_anexos_enviado_por_foreign FOREIGN KEY (enviado_por) REFERENCES public.users(id);


--
-- Name: proc_oficio_anexos proc_oficio_anexos_oficio_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_oficio_anexos
    ADD CONSTRAINT proc_oficio_anexos_oficio_id_foreign FOREIGN KEY (oficio_id) REFERENCES public.proc_oficios(id) ON DELETE CASCADE;


--
-- Name: proc_oficio_respostas proc_oficio_respostas_oficio_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_oficio_respostas
    ADD CONSTRAINT proc_oficio_respostas_oficio_id_foreign FOREIGN KEY (oficio_id) REFERENCES public.proc_oficios(id) ON DELETE CASCADE;


--
-- Name: proc_oficio_respostas proc_oficio_respostas_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_oficio_respostas
    ADD CONSTRAINT proc_oficio_respostas_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: proc_oficios proc_oficios_destinatario_unidade_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_oficios
    ADD CONSTRAINT proc_oficios_destinatario_unidade_id_foreign FOREIGN KEY (destinatario_unidade_id) REFERENCES public.ug_organograma(id) ON DELETE SET NULL;


--
-- Name: proc_oficios proc_oficios_destinatario_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_oficios
    ADD CONSTRAINT proc_oficios_destinatario_usuario_id_foreign FOREIGN KEY (destinatario_usuario_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: proc_oficios proc_oficios_remetente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_oficios
    ADD CONSTRAINT proc_oficios_remetente_id_foreign FOREIGN KEY (remetente_id) REFERENCES public.users(id);


--
-- Name: proc_oficios proc_oficios_ug_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_oficios
    ADD CONSTRAINT proc_oficios_ug_id_foreign FOREIGN KEY (ug_id) REFERENCES public.ugs(id) ON DELETE SET NULL;


--
-- Name: proc_processos proc_processos_aberto_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_processos
    ADD CONSTRAINT proc_processos_aberto_por_foreign FOREIGN KEY (aberto_por) REFERENCES public.users(id);


--
-- Name: proc_processos proc_processos_concluido_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_processos
    ADD CONSTRAINT proc_processos_concluido_por_foreign FOREIGN KEY (concluido_por) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: proc_processos proc_processos_etapa_atual_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_processos
    ADD CONSTRAINT proc_processos_etapa_atual_id_foreign FOREIGN KEY (etapa_atual_id) REFERENCES public.proc_tramitacoes(id) ON DELETE SET NULL;


--
-- Name: proc_processos proc_processos_tipo_processo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_processos
    ADD CONSTRAINT proc_processos_tipo_processo_id_foreign FOREIGN KEY (tipo_processo_id) REFERENCES public.proc_tipos_processo(id);


--
-- Name: proc_processos proc_processos_ug_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_processos
    ADD CONSTRAINT proc_processos_ug_id_foreign FOREIGN KEY (ug_id) REFERENCES public.ugs(id) ON DELETE SET NULL;


--
-- Name: proc_tipo_etapas proc_tipo_etapas_responsavel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_tipo_etapas
    ADD CONSTRAINT proc_tipo_etapas_responsavel_id_foreign FOREIGN KEY (responsavel_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: proc_tipo_etapas proc_tipo_etapas_tipo_processo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_tipo_etapas
    ADD CONSTRAINT proc_tipo_etapas_tipo_processo_id_foreign FOREIGN KEY (tipo_processo_id) REFERENCES public.proc_tipos_processo(id) ON DELETE CASCADE;


--
-- Name: proc_tipos_processo proc_tipos_processo_criado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_tipos_processo
    ADD CONSTRAINT proc_tipos_processo_criado_por_foreign FOREIGN KEY (criado_por) REFERENCES public.users(id);


--
-- Name: proc_tramitacoes proc_tramitacoes_destinatario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_tramitacoes
    ADD CONSTRAINT proc_tramitacoes_destinatario_id_foreign FOREIGN KEY (destinatario_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: proc_tramitacoes proc_tramitacoes_destino_unidade_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_tramitacoes
    ADD CONSTRAINT proc_tramitacoes_destino_unidade_id_foreign FOREIGN KEY (destino_unidade_id) REFERENCES public.ug_organograma(id) ON DELETE SET NULL;


--
-- Name: proc_tramitacoes proc_tramitacoes_processo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_tramitacoes
    ADD CONSTRAINT proc_tramitacoes_processo_id_foreign FOREIGN KEY (processo_id) REFERENCES public.proc_processos(id) ON DELETE CASCADE;


--
-- Name: proc_tramitacoes proc_tramitacoes_recebido_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_tramitacoes
    ADD CONSTRAINT proc_tramitacoes_recebido_por_foreign FOREIGN KEY (recebido_por) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: proc_tramitacoes proc_tramitacoes_remetente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_tramitacoes
    ADD CONSTRAINT proc_tramitacoes_remetente_id_foreign FOREIGN KEY (remetente_id) REFERENCES public.users(id);


--
-- Name: proc_tramitacoes proc_tramitacoes_tipo_etapa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proc_tramitacoes
    ADD CONSTRAINT proc_tramitacoes_tipo_etapa_id_foreign FOREIGN KEY (tipo_etapa_id) REFERENCES public.proc_tipo_etapas(id) ON DELETE SET NULL;


--
-- Name: ug_organograma ug_organograma_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ug_organograma
    ADD CONSTRAINT ug_organograma_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.ug_organograma(id) ON DELETE CASCADE;


--
-- Name: ug_organograma ug_organograma_responsavel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ug_organograma
    ADD CONSTRAINT ug_organograma_responsavel_id_foreign FOREIGN KEY (responsavel_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: ug_organograma ug_organograma_ug_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ug_organograma
    ADD CONSTRAINT ug_organograma_ug_id_foreign FOREIGN KEY (ug_id) REFERENCES public.ugs(id) ON DELETE CASCADE;


--
-- Name: user_ugs user_ugs_ug_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_ugs
    ADD CONSTRAINT user_ugs_ug_id_foreign FOREIGN KEY (ug_id) REFERENCES public.ugs(id) ON DELETE CASCADE;


--
-- Name: user_ugs user_ugs_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_ugs
    ADD CONSTRAINT user_ugs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: users users_ug_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_ug_id_foreign FOREIGN KEY (ug_id) REFERENCES public.ugs(id) ON DELETE SET NULL;


--
-- Name: users users_unidade_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_unidade_id_foreign FOREIGN KEY (unidade_id) REFERENCES public.ug_organograma(id) ON DELETE SET NULL;


--
-- PostgreSQL database dump complete
--

\unrestrict ZiChe2SLHjo2KT6h0xbcIUPSZBy4GSSXWtEQjjNOn3VcwZnPU4sHE3ExROjIybc

