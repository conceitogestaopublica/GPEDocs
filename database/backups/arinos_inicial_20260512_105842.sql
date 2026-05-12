--
-- PostgreSQL database dump
--

\restrict Jn51BlCdnhtvwPTv12Q7KEk9CVM26XwRO5KNsDCI289T42gIV0axEWXBKw0KaNJ

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
    timestamp_assinatura timestamp(0) without time zone,
    signature_position jsonb
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
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cache (key, value, expiration) FROM stdin;
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: ged_assinaturas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_assinaturas (id, solicitacao_id, documento_id, signatario_id, ordem, status, email_signatario, cpf_signatario, ip, geolocalizacao, user_agent, hash_documento, versao_id, motivo_recusa, assinado_em, created_at, updated_at, tipo_assinatura, certificado_id, assinatura_pkcs7, cadeia_certificados, politica_assinatura, algoritmo_hash, arquivo_assinado_path, hash_assinatura_sha256, timestamp_assinatura, signature_position) FROM stdin;
\.


--
-- Data for Name: ged_audit_logs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_audit_logs (id, documento_id, usuario_id, acao, detalhes, ip, user_agent, created_at) FROM stdin;
\.


--
-- Data for Name: ged_buscas_salvas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_buscas_salvas (id, usuario_id, nome, filtros, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: ged_certificados; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_certificados (id, user_id, tipo, subject_cn, subject_cpf, subject_dn, issuer_cn, issuer_dn, serial_number, thumbprint_sha1, thumbprint_sha256, valido_de, valido_ate, certificado_pem, cadeia_pem, politica_oid, icp_brasil, revogado, verificado_em, created_at, updated_at) FROM stdin;
1	94	A1	JOEL GONCALVES JARDIM:85183865604	85183865604	C=BR, O=ICP-Brasil, OU=Certificado Digital PF A1,Videoconferencia,32522131000125,AC SyngularID Multipla, CN=JOEL GONCALVES JARDIM:85183865604	AC SyngularID Multipla	C=BR, OU=AC SyngularID, O=ICP-Brasil, CN=AC SyngularID Multipla	B6FB0F469F3BA3A40727	f4803af5fd112347f1480abde8c2590172f04abd	3476aaa7686efb70f798fac92e7aa7a2a02c89f73dd794b054e26300323c02fa	2026-04-30 16:54:00	2027-04-30 16:54:00	-----BEGIN CERTIFICATE-----\nMIIHyjCCBbKgAwIBAgILALb7D0afO6OkBycwDQYJKoZIhvcNAQELBQAwWzELMAkG\nA1UEBhMCQlIxFjAUBgNVBAsMDUFDIFN5bmd1bGFySUQxEzARBgNVBAoMCklDUC1C\ncmFzaWwxHzAdBgNVBAMMFkFDIFN5bmd1bGFySUQgTXVsdGlwbGEwHhcNMjYwNDMw\nMTY1NDAwWhcNMjcwNDMwMTY1NDAwWjCBxzELMAkGA1UEBhMCQlIxEzARBgNVBAoM\nCklDUC1CcmFzaWwxIjAgBgNVBAsMGUNlcnRpZmljYWRvIERpZ2l0YWwgUEYgQTEx\nGTAXBgNVBAsMEFZpZGVvY29uZmVyZW5jaWExFzAVBgNVBAsMDjMyNTIyMTMxMDAw\nMTI1MR8wHQYDVQQLDBZBQyBTeW5ndWxhcklEIE11bHRpcGxhMSowKAYDVQQDDCFK\nT0VMIEdPTkNBTFZFUyBKQVJESU06ODUxODM4NjU2MDQwggEiMA0GCSqGSIb3DQEB\nAQUAA4IBDwAwggEKAoIBAQClLn9Z7TlWRyC/5SFNowiTCq/4CdxguqKSNI25baWk\nBegj2lbkReCCZmIOVMO6STz1gY+5aQMddt4PgiDCz2cYyUqunXs1uOoRsRMedHy1\nbAmXFeyYdsiwyS6EJY+uTQLV46lB+sI0FwlliNfYncFKzV16KhPlT5Ir3YdEue2E\nfsUFE+ayKS/p/ABx+USI065kiFMaCY6KRLY9itHZd1k4wS9J9hpxfIpeVpx9Dqan\nGQLMgZ3XY6J1OuckkLoXcwB6HQbHkAvA7M9ATh64bli5rb+jWTsG8rCLyn1mNJEw\nji3/hAcVPCCdD52T7dY88fxK/WHXA1tDw95og/uqepcvAgMBAAGjggMgMIIDHDAO\nBgNVHQ8BAf8EBAMCBeAwHQYDVR0lBBYwFAYIKwYBBQUHAwQGCCsGAQUFBwMCMAkG\nA1UdEwQCMAAwHwYDVR0jBBgwFoAUk+H/fh3l9eRN4TliiyFpleavchYwHQYDVR0O\nBBYEFNHD9fVMKkEAj0TAH2QknrtDbeP4MH8GCCsGAQUFBwEBBHMwcTBvBggrBgEF\nBQcwAoZjaHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3JlcG9zaXRvcmlvL2FjLXN5\nbmd1bGFyaWQtbXVsdGlwbGEvY2VydGlmaWNhZG9zL2FjLXN5bmd1bGFyaWQtbXVs\ndGlwbGEucDdiMIGCBgNVHSAEezB5MHcGB2BMAQIBgQUwbDBqBggrBgEFBQcCARZe\naHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3JlcG9zaXRvcmlvL2FjLXN5bmd1bGFy\naWQtbXVsdGlwbGEvZHBjL2RwYy1hYy1zeW5ndWxhcklELW11bHRpcGxhLnBkZjCB\ntAYDVR0RBIGsMIGpoEIGBWBMAQMBoDkENzAzMTAxOTcyODUxODM4NjU2MDQwMDAw\nMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDCgNAYFYEwBAwWgKwQpMDAw\nMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDCgFwYFYEwBAwag\nDgQMMDAwMDAwMDAwMDAwgRRqb2VsamFyZGltQGdtYWlsLmNvbTCB4gYDVR0fBIHa\nMIHXMGSgYqBghl5odHRwOi8vc3luZ3VsYXJpZC5jb20uYnIvcmVwb3NpdG9yaW8v\nYWMtc3luZ3VsYXJpZC1tdWx0aXBsYS9sY3IvbGNyLWFjLXN5bmd1bGFyaWQtbXVs\ndGlwbGEuY3JsMG+gbaBrhmlodHRwOi8vaWNwLWJyYXNpbC5zeW5ndWxhcmlkLmNv\nbS5ici9yZXBvc2l0b3Jpby9hYy1zeW5ndWxhcmlkLW11bHRpcGxhL2xjci9sY3It\nYWMtc3luZ3VsYXJpZC1tdWx0aXBsYS5jcmwwDQYJKoZIhvcNAQELBQADggIBAAid\nwb043Ztl4RgQtGLwcnYpzFsq+koraPg0NYRXk96nBSgxTtX3aWTHKWQAMU/aOySL\nvjjE9b3nYNhbNeMDnlTfh/vfh/GvHKbkDcw4F1UoW8cgn6LHgN/ckLsO+3XonU8o\ndPsW8DJOGhol36En0Gn2A7jIbxi84Upi6vJmrNzKB8GeQSUv9aNCahpFOMpMPSfl\nsU7ciTMFx5wHa4CC7fSNrhCEOWbuwLHd/W0KyECHVscsWim2h7DyztICmjNREYAQ\nMSh5AIB2sKWeO7Fp/NPmwU7wEx8WkgCuvkiip1tJ1vFxwV3ziBrtNDqk6gNjFxh9\nNYREgwJfeMyzp+hT2IkAH0b2IHZN0PACVaWk98zg4SZUp8UklXEH1TYRjXj9/6/i\n9KWHCI+L/o5UcoiGYMqbUJUm2+36wqq6OtZ4NbvJpe9PJpVZ/VY01IsgRx0RZCRZ\n5eW3cN+axPOh5HtOEv+nfK9vuoSnbaYRc/BS3Rg46Wt+IlypzWTY0lRRJbDSMZrg\nB2NYAr8XwFnmJg4ZKETJ09kIogW59JKctf2dGXrHGTquSKosiz/ImIY8BAReUx6H\nbw05L8XjSeOfELPhTxMEY3KoFS0b93J1vW9t4dxg88R89lGLrvgEw9w3o/p1EbfK\nmC0wwhxtg0bvyv6Wwqznyr8K18tfv8WIkyuNqgMI\n-----END CERTIFICATE-----\n	["-----BEGIN CERTIFICATE-----\\nMIIGSDCCBDCgAwIBAgIJAOsvRfLjYt7QMA0GCSqGSIb3DQEBDQUAMIGXMQswCQYD\\nVQQGEwJCUjETMBEGA1UECgwKSUNQLUJyYXNpbDE9MDsGA1UECww0SW5zdGl0dXRv\\nIE5hY2lvbmFsIGRlIFRlY25vbG9naWEgZGEgSW5mb3JtYWNhbyAtIElUSTE0MDIG\\nA1UEAwwrQXV0b3JpZGFkZSBDZXJ0aWZpY2Fkb3JhIFJhaXogQnJhc2lsZWlyYSB2\\nNTAeFw0yMjAzMjExODAwMjFaFw0yOTAzMDIxMjAwMjFaMHAxCzAJBgNVBAYMAkJS\\nMRMwEQYDVQQKDApJQ1AtQnJhc2lsMTQwMgYDVQQLDCtBdXRvcmlkYWRlIENlcnRp\\nZmljYWRvcmEgUmFpeiBCcmFzaWxlaXJhIHY1MRYwFAYDVQQDDA1BQyBTeW5ndWxh\\ncklEMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAp+di0cX1UCcm8bp7\\nwt1Nz7quHmM7tGPH9ri6\\/6CmIP3hy3Ww4ivFxOJSYsUwoxJcs4SiA3oOr7IAr6VR\\nkYo1eohcE+ZQOEnE7Id5AD19dBeOmcgYfrH7kOKSrmFeaJXs+KCAXgd04WzqVRKM\\nMsoEIIps9SHUwwL4KSR3Ecyyd3mMlJPBtyoaPqBz+e8nY3i\\/JVFvVIoTxoBKJqJy\\nUtvS0l0baiZUPBtG3zd5es65OM4h2tYkrWDHvWoffHaQdIJC2ke4SIojX0ozTWae\\nMd0Az4USCKfb\\/MhFg2WtwalWKZOBKGiG8fpsKErq9gxoGpoLmqkvq3AlHk6bpkhf\\nnEs\\/LpdV0TfbeCs\\/weK63UoMXqSOMkIr8eO9mLjZoXg5xDiF5TW1EH1XFY2j91OD\\ngZ+xlOf6KO+\\/2NA26nJfHbeJwm0eRg5rgPZFCUdk9Lf9rUXEDqidZwQmofxa6OGN\\n9lr0Sed4HpQegeacyfbOrBB8MfcOMqUf9oZvimDlqXEQUaRkgN2DJ5Eqmw32AE1A\\nPdqQ\\/nt0brI6PVt4oEdcV06wb7X2aWSc2C3V582JqTv0g6I2901hesvfadVbOLA1\\n18uNodiu2lxji\\/e4mSaXcEEO4u30hNtU\\/6zXANOh0Jtf5X8lHczaNFqj2BT9AxdJ\\njlFWBZ2v7SwEcCTKLSFBLBxTN+UCAwEAAaOBvDCBuTAOBgNVHQ8BAf8EBAMCAQYw\\nFQYDVR0gBA4wDDAKBgZgTAEBgRwwADA\\/BgNVHR8EODA2MDSgMqAwhi5odHRwOi8v\\nYWNyYWl6LmljcGJyYXNpbC5nb3YuYnIvTENSYWNyYWl6djUuY3JsMB8GA1UdIwQY\\nMBaAFGmovnXZxO9s5xNF5GFu5Wj4tkBeMB0GA1UdDgQWBBRQOH1C5FHJB0NYY8Br\\nY6z\\/oHPz4DAPBgNVHRMBAf8EBTADAQH\\/MA0GCSqGSIb3DQEBDQUAA4ICAQB9ZGip\\nb+KsqGoaeE5p8BeGFnbj8UXfEoXBP5K1ggj6MUuzj33HvZqvrJ06uVOpIFlUX60A\\nNxYsSexMDqSzgXYcb21YcxbRcD1fYdq5lqk759i9BeGK6SvfyNeKaEwpdhBQK24v\\nkT1nxP7MeyN8vLwldchNlM28GrRuUwwHTOohN973juPwAdnJUIAxPjvZzzfNs2Oq\\n4\\/ksQbFgObb6ltBRDvS20J4wBUbDSjkkSww2gQP08NFtQXB\\/1vpFwP6wdfwpmIQ5\\ntHpi0UBW1rJJZ9AqGhS3ciB1om1chG9iRa\\/QzBqCHHGN\\/0hlrdsZMxEKdosuvNPp\\nhaJwlNS+ffo79KWPp6KLerx1Nq6QIVLTyqvWIqmpRjjFTv7dxwoFr8ioq+81K\\/nR\\notMu5D0CeqqzUXlbCVtLDOIMUSOVQ61IBHT1NwoVeABOl2qEdJ+sAxDuxFzIyh9F\\nVhBE4vnHaDArb28yESBvhUQmoEGrTSp4Ee8ynu3VkXI9hxVsQGooZbf0CpE5RKWj\\n2TLW0dvxIyGc1yH5LLMy75ejIyoskN6rSkO8mCy6bBOqtW5RpU7eZG+257hZ8y5Z\\nL67VWX+eHyMudrUw10gBG4dy\\/sdg2r82QAL1iuqPd37ZHy4GNKurtYUPou6IZ18x\\nPPs\\/KWglKa+00PErdGlLgNIYv8Y6QBabf9G6LQ==\\n-----END CERTIFICATE-----\\n","-----BEGIN CERTIFICATE-----\\nMIIGoTCCBImgAwIBAgIBATANBgkqhkiG9w0BAQ0FADCBlzELMAkGA1UEBhMCQlIx\\nEzARBgNVBAoMCklDUC1CcmFzaWwxPTA7BgNVBAsMNEluc3RpdHV0byBOYWNpb25h\\nbCBkZSBUZWNub2xvZ2lhIGRhIEluZm9ybWFjYW8gLSBJVEkxNDAyBgNVBAMMK0F1\\ndG9yaWRhZGUgQ2VydGlmaWNhZG9yYSBSYWl6IEJyYXNpbGVpcmEgdjUwHhcNMTYw\\nMzAyMTMwMTM4WhcNMjkwMzAyMjM1OTM4WjCBlzELMAkGA1UEBhMCQlIxEzARBgNV\\nBAoMCklDUC1CcmFzaWwxPTA7BgNVBAsMNEluc3RpdHV0byBOYWNpb25hbCBkZSBU\\nZWNub2xvZ2lhIGRhIEluZm9ybWFjYW8gLSBJVEkxNDAyBgNVBAMMK0F1dG9yaWRh\\nZGUgQ2VydGlmaWNhZG9yYSBSYWl6IEJyYXNpbGVpcmEgdjUwggIiMA0GCSqGSIb3\\nDQEBAQUAA4ICDwAwggIKAoICAQD3LXgabUWsF+gUXw\\/6YODeF2XkqEyfk3VehdsI\\nx+3\\/ERgdjCS\\/ouxYR0Epi2hdoMUVJDNf3XQfjAWXJyCoTneHYAl2McMdvoqtLB2i\\nleQlJiis0fTtYTJayee9BAIdIrCor1Lc0vozXCpDtq5nTwhjIocaZtcuFsdrkl+n\\nbfYxl5m7vjTkTMS6j8ffjmFzbNPDlJuV3Vy7AzapPVJrMl6UHPXCHMYMzl0KxR\\/4\\n7S5XGgmLYkYt8bNCHA3fg07y+Gtvgu+SNhMPwWKIgwhYw+9vErOnavRhOimYo4M2\\nAwNpNK0OKLI7Im5V094jFp4Ty+mlmfQH00k8nkSUEN+1TGGkhv16c2hukbx9iCfb\\nmk7im2hGKjQA8eH64VPYoS2qdKbPbd3xDDHN2croYKpy2U2oQTVBSf9hC3o6fKo3\\nzp0U3dNiw7ZgWKS9UwP31Q0gwgB1orZgLuF+LIppHYwxcTG\\/AovNWa4sTPukMiX2\\nL+p7uIHExTZJJU4YoDacQh\\/mfbPIz3261He4YFmQ35sfw3eKHQSOLyiVfev\\/n0l\\/\\nr308PijEd+d+Hz5RmqIzS8jYXZIeJxym4mEjE1fKpeP56Ea52LlIJ8ZqsJ3xzHWu\\n3WkAVz4hMqrX6BPMGW2IxOuEUQyIaCBg1lI6QLiPMHvo2\\/J7gu4YfqRcH6i27W3H\\nyzamEQIDAQABo4H1MIHyME4GA1UdIARHMEUwQwYFYEwBAQAwOjA4BggrBgEFBQcC\\nARYsaHR0cDovL2FjcmFpei5pY3BicmFzaWwuZ292LmJyL0RQQ2FjcmFpei5wZGYw\\nPwYDVR0fBDgwNjA0oDKgMIYuaHR0cDovL2FjcmFpei5pY3BicmFzaWwuZ292LmJy\\nL0xDUmFjcmFpenY1LmNybDAfBgNVHSMEGDAWgBRpqL512cTvbOcTReRhbuVo+LZA\\nXjAdBgNVHQ4EFgQUaai+ddnE72znE0XkYW7laPi2QF4wDwYDVR0TAQH\\/BAUwAwEB\\n\\/zAOBgNVHQ8BAf8EBAMCAQYwDQYJKoZIhvcNAQENBQADggIBABRt2\\/JiWapef7o\\/\\nplhR4PxymlMIp\\/JeZ5F0BZ1XafmYpl5g6pRokFrIRMFXLyEhlgo51I05InyCc9Td\\n6UXjlsOASTc\\/LRavyjB\\/8NcQjlRYDh6xf7OdP05mFcT\\/0+6bYRtNgsnUbr10pfsK\\n\\/UzyUvQWbumGS57hCZrAZOyd9MzukiF\\/azAa6JfoZk2nDkEudKOY8tRyTpMmDzN5\\nfufPSC3v7tSJUqTqo5z7roN\\/FmckRzGAYyz5XulbOc5\\/UsAT\\/tk+KP\\/clbbqd\\/hh\\nevmmdJclLr9qWZZcOgzuFU2YsgProtVu0fFNXGr6KK9fu44pOHajmMsTXK3X7r\\/P\\nwh19kFRow5F3RQMUZC6Re0YLfXh+ypnUSCzA+uL4JPtHIGyvkbWiulkustpOKUSV\\nwBPzvA2sQUOvqdbAR7C8jcHYFJMuK2HZFji7pxcWWab\\/NKsFcJ3sluDjmhizpQax\\nbYTfAVXu3q8yd0su\\/BHHhBpteyHvYyyz0Eb9LUysR2cMtWvfPU6vnoPgYvOGO1Cz\\niyGEsgKULkCH4o2Vgl1gQuKWO4V68rFW8a\\/jvq28sbY+y\\/Ao0I5ohpnBcQOAawiF\\nbz6yJtObajYMuztDDP8oY656EuuJXBJhuKAJPI\\/7WDtgfV8ffOh\\/iQGQATVMtgDN\\n0gv8bn5NdUX8UMNX1sHhU3H1UpoW\\n-----END CERTIFICATE-----\\n","-----BEGIN CERTIFICATE-----\\nMIIHUjCCBTqgAwIBAgIKcGwrRiXa9i64QTANBgkqhkiG9w0BAQ0FADBwMQswCQYD\\nVQQGDAJCUjETMBEGA1UECgwKSUNQLUJyYXNpbDE0MDIGA1UECwwrQXV0b3JpZGFk\\nZSBDZXJ0aWZpY2Fkb3JhIFJhaXogQnJhc2lsZWlyYSB2NTEWMBQGA1UEAwwNQUMg\\nU3luZ3VsYXJJRDAeFw0yMjA0MTgxODM1MTRaFw0yOTAzMDEyMzU5NTlaMFsxCzAJ\\nBgNVBAYTAkJSMRYwFAYDVQQLDA1BQyBTeW5ndWxhcklEMRMwEQYDVQQKDApJQ1At\\nQnJhc2lsMR8wHQYDVQQDDBZBQyBTeW5ndWxhcklEIE11bHRpcGxhMIICIjANBgkq\\nhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAnfheqOqUO3wiQAuJxnAb+F0OAVxBMN+T\\nZEwyVvSbCni4Ln5XNhs\\/fz7jfB3mGDs1IptiXNcJiRDgOy7tzukwPVRgLbKlVy52\\nkq\\/tr83cbskJcS6FfsO6T22xfOEO8uZ1uJ+jTPYOyBFjjOXBx9XB4NbNcpCE8KL9\\n+WStpmLS77\\/IUqbkUsD3oA+jHyHmnHqNayTmKnr4z\\/OxiTxNNayEsK2yiO686vkn\\np+5dy6G3axwlmQkbsXKnVeyKN4IuKUBTDKrxSmDuHievofjT\\/YwJ\\/DiT19lUOWoQ\\nGKhUax7WnWCw6ufPcPn1NsskZIlCPwKoY8RR4zCuLO8pzb\\/hCNoUAf0TNjnS9gHP\\n1hVE0PDqPxB9OT7ejMtmPAWLIw64\\/2m9cy8lV8UXO1bX1tToMF+q0pA\\/LTwDytdw\\n1AoM\\/OcNqVZRLxa59RZsuLbLPLC0tzRe13CzXBgecbotmKNi2hAaWMEyfjmvcJFV\\nI9CUqlmNenxUIf9huy7nrNAROLC47AmrTmuFIvlAhRBb7mu4GrYfsUg\\/IuKhCXHp\\nBpT01aBdFHAz0BYgdJVd6PvzyD9wkE9Mtfb81AFq9eWgOnt6CyD1DZl5cCv6dpW\\/\\nULXM+ObExX4hvgrH8yZHZZOsIKpikYodILZSgRm1q9JefYsa8nRlg\\/WGgIsgMevX\\nuhzwLf\\/5XU8CAwEAAaOCAgEwggH9MA4GA1UdDwEB\\/wQEAwIBBjAPBgNVHRMBAf8E\\nBTADAQH\\/MB8GA1UdIwQYMBaAFFA4fULkUckHQ1hjwGtjrP+gc\\/PgMB0GA1UdDgQW\\nBBST4f9+HeX15E3hOWKLIWmV5q9yFjCB2AYDVR0gBIHQMIHNMGUGB2BMAQIBgQUw\\nWjBYBggrBgEFBQcCARZMaHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3JlcG9zaXRv\\ncmlvL2FjLXN5bmd1bGFyaWQvZHBjL2RwYy1hYy1zeW5ndWxhcklELnBkZjBkBgZg\\nTAECA30wWjBYBggrBgEFBQcCARZMaHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3Jl\\ncG9zaXRvcmlvL2FjLXN5bmd1bGFyaWQvZHBjL2RwYy1hYy1zeW5ndWxhcklELnBk\\nZjCBvgYDVR0fBIG2MIGzMFKgUKBOhkxodHRwOi8vc3luZ3VsYXJpZC5jb20uYnIv\\ncmVwb3NpdG9yaW8vYWMtc3luZ3VsYXJpZC9sY3IvbGNyLWFjLXN5bmd1bGFyaWQu\\nY3JsMF2gW6BZhldodHRwOi8vaWNwLWJyYXNpbC5zeW5ndWxhcmlkLmNvbS5ici9y\\nZXBvc2l0b3Jpby9hYy1zeW5ndWxhcmlkL2xjci9sY3ItYWMtc3luZ3VsYXJpZC5j\\ncmwwDQYJKoZIhvcNAQENBQADggIBAJbsTc4B20N0qJj6bCSYsNy1E0WN9Bqmqs8u\\nrDBy7if6LNnxRbPNDkFZbE5SI\\/JxA4\\/XYx2tMzjdxdIZGGTTJFoP0V2yNZNXT9s5\\nAb\\/ksFFXex8eSandd1EraXzoUHDmrVdF\\/LTUSqNZdzvZvPglCHkTXoxMJJycMvay\\nOT6asVy9UWqCiVJvZFA8oOXvLSwR8Dt6M2NcBK9NpDaaqgjGKZlHeK2hDMNgRUaV\\nWK9QuWUwlJUMqK8U8Qi51iOJkM9jpv1Fg460TZqHU7BwLU1YoI7ADa2soVHYNcZa\\naWKO6L+74d6j3TueQ8jcnHw8moXV4zYSsMQau+yA5IlRlDYXQl4iCcG2wBbEAMNu\\nJUCmgg2G+jihAQfXWR\\/JRDCBaNPrFqVJPkZqGKqN60gCav6cxbYKH2ZSipY9nO7W\\n3sStJjIp5dUk55LVAdGMPc9IYDiYMR57TKZm+QX\\/zT6bliA4Lr0EnYeOP\\/Qvl2iR\\nSrL6dSAgyqxpZa2hH75ww+zWsO5qAbnCYwIkTvidixXOap5VBJYAG1d+o+IQ9+hQ\\ndtKCq46rQCeOV0L87lNCdC7iadLRxlYfePohfYY0avR2im1lxlTPPLUVfYPF3tGY\\nEEcQoU6JSdCnI9SBz0UOHNgKUc+rK9V034eCqiSCUUY+l8ifaEAdtMZnYpx5k+TL\\nCAwHh668\\n-----END CERTIFICATE-----\\n"]	2.16.76.1.2.1.133	t	f	2026-05-12 12:57:42	2026-04-30 21:01:38	2026-05-12 12:57:42
\.


--
-- Data for Name: ged_compartilhamentos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_compartilhamentos (id, documento_id, usuario_id, permissao, criado_por, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: ged_documento_tags; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_documento_tags (documento_id, tag_id) FROM stdin;
\.


--
-- Data for Name: ged_documentos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_documentos (id, nome, descricao, tipo_documental_id, pasta_id, versao_atual, tamanho, mime_type, autor_id, status, classificacao, ocr_texto, check_out_por, check_out_em, created_at, updated_at, deleted_at, qr_code_token, ug_id, sistema_origem, numero_externo, metadados_externos, callback_url, callback_executado, callback_executado_em) FROM stdin;
\.


--
-- Data for Name: ged_favoritos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_favoritos (user_id, documento_id, created_at) FROM stdin;
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
-- Data for Name: ged_metadados; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_metadados (id, documento_id, chave, valor, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: ged_notificacoes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_notificacoes (id, usuario_id, tipo, titulo, mensagem, referencia_tipo, referencia_id, lida, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: ged_pastas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_pastas (id, nome, descricao, parent_id, path, criado_por, created_at, updated_at, ativo, ug_id) FROM stdin;
4	GAB	Gabinete do Prefeito	\N	GAB	94	2026-05-01 19:05:19	2026-05-01 20:25:57	t	\N
1	SME	Secretaria Municipal de Educação	\N	SME	94	2026-05-01 19:04:45	2026-05-01 20:26:17	t	\N
2	SMG	Secretaria Municipal de Governo	\N	SMG	94	2026-05-01 19:04:57	2026-05-01 20:26:58	t	\N
3	SMS	Secretaria Municipal de Saúde	\N	SMS	94	2026-05-01 19:05:08	2026-05-01 20:27:21	t	\N
5	Relatorios Mensais	Relatórios do Fechamento Mensal	\N	Relatorios Mensais	94	2026-05-03 20:14:57	2026-05-03 20:14:57	t	\N
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
1	gpe	GPE - Sistema de Gestao Publica	Sistema legado que envia empenhos, liquidacoes e pagamentos	7d4b52516088904c17dfb8cc038fffb7965dbb372e9269ed157d25640d77e7c7	V3m3g7H3	t	2026-05-11 13:13:31	2026-05-02 15:09:48	2026-05-11 13:13:31	BBoliocwLkDrS4C5p6fqgmUOAxJSAFgnfTMfmPWJriJt7dtx	\N
\.


--
-- Data for Name: ged_solicitacoes_assinatura; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_solicitacoes_assinatura (id, documento_id, solicitante_id, status, mensagem, prazo, created_at, updated_at) FROM stdin;
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
94	1
94	2
94	3
94	4
\.


--
-- Data for Name: ged_versoes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_versoes (id, documento_id, versao, arquivo_path, tamanho, hash_sha256, autor_id, comentario, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: ged_webhook_logs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ged_webhook_logs (id, sistema_origem, documento_id, evento, callback_url, payload, signature_header, sucesso, http_status, response_body, erro, tentativas, duracao_ms, enviado_em, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
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
\.


--
-- Data for Name: portal_categorias_servicos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.portal_categorias_servicos (id, ug_id, nome, slug, icone, cor, descricao, ordem, ativo, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: portal_cidadaos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.portal_cidadaos (id, nome, email, cpf, telefone, senha, email_verificado_em, token_verificacao, ativo, remember_token, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: portal_servicos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.portal_servicos (id, ug_id, categoria_id, titulo, slug, publico_alvo, descricao_curta, descricao_completa, requisitos, documentos_necessarios, prazo_entrega, custo, canais, orgao_responsavel, legislacao, palavras_chave, icone, publicado, visualizacoes, ordem, created_at, updated_at, permite_anonimo, setor_responsavel_id, tipo_processo_id) FROM stdin;
\.


--
-- Data for Name: portal_solicitacao_eventos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.portal_solicitacao_eventos (id, solicitacao_id, tipo, autor_tipo, autor_nome, autor_user_id, autor_cidadao_id, status_anterior, status_novo, mensagem, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: portal_solicitacoes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.portal_solicitacoes (id, codigo, ug_id, servico_id, cidadao_id, status, descricao, telefone_contato, email_contato, atendente_id, resposta, respondida_em, created_at, updated_at, anonima, processo_id) FROM stdin;
\.


--
-- Data for Name: proc_anexos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proc_anexos (id, processo_id, tramitacao_id, nome, arquivo_path, tamanho, mime_type, hash_sha256, enviado_por, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: proc_circular_anexos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proc_circular_anexos (id, circular_id, nome, arquivo_path, tamanho, mime_type, enviado_por, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: proc_circular_destinatarios; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proc_circular_destinatarios (id, circular_id, usuario_id, lido, lido_em, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: proc_circulares; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proc_circulares (id, numero, assunto, conteudo, remetente_id, setor_origem, destino_tipo, destino_setores, status, enviado_em, arquivado_em, data_arquivamento_auto, qr_code_token, created_at, updated_at, deleted_at, ug_id, documento_id) FROM stdin;
\.


--
-- Data for Name: proc_comentarios; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proc_comentarios (id, processo_id, tramitacao_id, usuario_id, texto, interno, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: proc_historico; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proc_historico (id, processo_id, usuario_id, acao, detalhes, ip, user_agent, created_at) FROM stdin;
\.


--
-- Data for Name: proc_memorando_anexos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proc_memorando_anexos (id, memorando_id, nome, arquivo_path, tamanho, mime_type, enviado_por, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: proc_memorando_destinatarios; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proc_memorando_destinatarios (id, memorando_id, usuario_id, setor_destino, lido, lido_em, created_at, updated_at, unidade_id) FROM stdin;
\.


--
-- Data for Name: proc_memorando_respostas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proc_memorando_respostas (id, memorando_id, usuario_id, conteudo, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: proc_memorando_tramitacoes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proc_memorando_tramitacoes (id, memorando_id, tramite_origem_id, origem_usuario_id, origem_unidade_id, destino_usuario_id, destino_unidade_id, parecer, em_uso, finalizado, despachado_em, recebido_em, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: proc_memorandos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proc_memorandos (id, numero, assunto, conteudo, remetente_id, setor_origem, confidencial, status, enviado_em, arquivado_em, data_arquivamento_auto, qr_code_token, created_at, updated_at, deleted_at, ug_id, documento_id) FROM stdin;
\.


--
-- Data for Name: proc_oficio_anexos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proc_oficio_anexos (id, oficio_id, nome, arquivo_path, tamanho, mime_type, solicitar_assinatura, enviado_por, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: proc_oficio_respostas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proc_oficio_respostas (id, oficio_id, respondente_nome, respondente_email, conteudo, externo, usuario_id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: proc_oficios; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proc_oficios (id, numero, assunto, conteudo, remetente_id, setor_origem, destinatario_nome, destinatario_email, destinatario_cargo, destinatario_orgao, status, enviado_em, entregue_em, lido_em, arquivado_em, rastreio_token, qr_code_token, created_at, updated_at, deleted_at, ug_id, destinatario_usuario_id, destinatario_unidade_id, lido_em_interno, documento_id) FROM stdin;
\.


--
-- Data for Name: proc_processos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proc_processos (id, numero_protocolo, tipo_processo_id, assunto, descricao, dados_formulario, requerente_nome, requerente_cpf, requerente_email, requerente_telefone, setor_origem, etapa_atual_id, status, prioridade, aberto_por, concluido_por, concluido_em, observacao_conclusao, created_at, updated_at, deleted_at, ug_id, decisao, solicitacao_assinatura_id, documento_decisao_id) FROM stdin;
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
-- Data for Name: proc_tramitacoes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proc_tramitacoes (id, processo_id, tipo_etapa_id, ordem, setor_origem, setor_destino, remetente_id, destinatario_id, recebido_por, status, despacho, parecer, sla_horas, prazo, recebido_em, despachado_em, created_at, updated_at, destino_unidade_id, lida_em) FROM stdin;
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
\.


--
-- Data for Name: ug_organograma; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ug_organograma (id, ug_id, parent_id, nivel, codigo, nome, ativo, created_at, updated_at, endereco_proprio, cep, logradouro, numero, complemento, bairro, cidade, uf, legado_id, legado_tipo, dt_inicio, dt_encerramento, tipo_orgao, tipo_fundo, codigo_tce, suprimir_tce, responsavel_id, protocolo_externo) FROM stdin;
1	1	\N	1	1	Gabinete e Secretaria da Camara	t	2026-05-12 10:49:22	2026-05-12 10:49:22	f	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	f	\N	f
2	2	\N	1	4	Secretaria de Financas	t	2026-05-12 10:49:22	2026-05-12 10:49:22	f	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	f	\N	f
3	1	1	2	1	Gabinete e Secretaria da Camara	t	2026-05-12 10:49:22	2026-05-12 10:49:22	f	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	f	\N	f
4	2	2	2	1	Secretaria de Financas	t	2026-05-12 10:49:22	2026-05-12 10:49:22	f	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	f	\N	f
\.


--
-- Data for Name: ugs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ugs (id, codigo, nome, cnpj, nivel_1_label, nivel_2_label, nivel_3_label, ativo, observacoes, created_at, updated_at, cep, logradouro, numero, complemento, bairro, cidade, uf, legado_orgao_id, brasao_path, telefone, email_institucional, site, portal_slug, banner_path, banner_titulo, banner_subtitulo, banner_link_url, banner_link_label, banner_ativo) FROM stdin;
2	PMA	MUNICIPIO DE ARINOS	18.125.120/0001-80	Órgão	Unidade	Setor	t	\N	2026-05-12 10:49:22	2026-05-12 10:49:22	38680-000	Rua Francisco Pereira	2231	\N	Centro	Arinos	MG	\N	gestora/brasao_arinos.png	(38) 3635-2532	contabilidade@arinos.mg.gov.br	\N	\N	\N	\N	\N	\N	\N	t
1	CMA	CAMARA MUNICIPAL DE ARINOS	20.571.972/0001-43	Órgão	Unidade	Setor	t	\N	2026-05-12 10:49:22	2026-05-12 13:56:57	38680-000	Rua Professor Benevides	385	\N	Centro	Arinos	MG	\N	gestora/brasao_arinos.png	(38) 3635-1347	camaraarinos@hotmail.com	https://camaraarinos.mg.gov.br	cmarinos	\N	\N	\N	\N	\N	t
\.


--
-- Data for Name: user_ugs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.user_ugs (id, user_id, ug_id, principal, created_at, updated_at) FROM stdin;
12	194	2	t	2026-05-12 10:49:22	2026-05-12 10:49:22
13	195	2	t	2026-05-12 10:49:22	2026-05-12 10:49:22
14	196	2	t	2026-05-12 10:49:22	2026-05-12 10:49:22
15	197	2	t	2026-05-12 10:49:22	2026-05-12 10:49:22
16	198	2	t	2026-05-12 10:49:22	2026-05-12 10:49:22
17	199	2	t	2026-05-12 10:49:22	2026-05-12 10:49:22
18	200	2	t	2026-05-12 10:49:22	2026-05-12 10:49:22
19	201	2	t	2026-05-12 10:49:22	2026-05-12 10:49:22
20	202	2	t	2026-05-12 10:49:22	2026-05-12 10:49:22
21	203	2	t	2026-05-12 10:49:22	2026-05-12 10:49:22
22	204	2	t	2026-05-12 10:49:22	2026-05-12 10:49:22
23	205	2	t	2026-05-12 10:49:22	2026-05-12 10:49:22
24	206	2	t	2026-05-12 10:49:22	2026-05-12 10:49:22
25	207	2	t	2026-05-12 10:49:22	2026-05-12 10:49:22
26	208	2	t	2026-05-12 10:49:22	2026-05-12 10:49:22
28	194	1	f	2026-05-12 10:49:22	2026-05-12 10:49:22
29	195	1	f	2026-05-12 10:49:22	2026-05-12 10:49:22
30	196	1	f	2026-05-12 10:49:22	2026-05-12 10:49:22
31	197	1	f	2026-05-12 10:49:22	2026-05-12 10:49:22
32	198	1	f	2026-05-12 10:49:22	2026-05-12 10:49:22
33	199	1	f	2026-05-12 10:49:22	2026-05-12 10:49:22
34	200	1	f	2026-05-12 10:49:22	2026-05-12 10:49:22
35	201	1	f	2026-05-12 10:49:22	2026-05-12 10:49:22
36	202	1	f	2026-05-12 10:49:22	2026-05-12 10:49:22
37	203	1	f	2026-05-12 10:49:22	2026-05-12 10:49:22
38	204	1	f	2026-05-12 10:49:22	2026-05-12 10:49:22
39	205	1	f	2026-05-12 10:49:22	2026-05-12 10:49:22
40	206	1	f	2026-05-12 10:49:22	2026-05-12 10:49:22
41	207	1	f	2026-05-12 10:49:22	2026-05-12 10:49:22
42	208	1	f	2026-05-12 10:49:22	2026-05-12 10:49:22
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, cpf, tipo, ug_id, unidade_id, legado_usuario_id, super_admin, acesso_geral_ug) FROM stdin;
94	Joel Gonçalves Jardim	joeljardim@gmail.com	\N	$2y$12$5MVX1JSFMU522Wgn8fIagOpUl8WgY4LENL52vXYL3vC6iXHZbhdem	\N	2026-04-30 18:02:23	2026-05-12 12:18:00	85183865604	interno	\N	\N	\N	t	t
95	Administrador	admin@ged.local	\N	$2y$12$lS50fHK48Bz46PFvxQmLQOB8INA00wX7CtSYX26aLdYrTWelFqIKe	\N	2026-04-30 18:02:23	2026-05-12 12:18:01	\N	interno	\N	\N	\N	t	f
194	DIONE ALVES VIEIRA	dione-bada@hotmail.com	\N	$2y$10$zpvauAf/RwexJEW7biM8aO8tfE7WkkBXRuBau84PzK..UswcU3Qi6	\N	2026-05-12 10:49:22	2026-05-12 10:49:22	09611089678	interno	\N	\N	10007	t	f
195	Leticia Venceslau Ramos	leticiavenceslaur@gmail.com	\N	$2y$10$1wZrDLzxyg.3nYgxtaCCpe76mkUf.EpPNvO43ZgeBQhivyuZsv5S6	\N	2026-05-12 10:49:22	2026-05-12 10:49:22	11869433602	interno	\N	\N	10008	t	f
196	RICARDO ANTONIO FERREIRA NERY	ricardonery14@hotmail.com	\N	$2y$10$WLfCisLz0QuXt7CCDkKfMOYxaP6PbcDJJWDRk8O4DvvHoe4grvd4S	\N	2026-05-12 10:49:22	2026-05-12 10:49:22	01330401190	interno	\N	\N	10010	t	f
197	CLODESLEY FERREIRA DE ALMEIDA	clodesley@camara.com.br	\N	$2y$10$4L/8E2lw1D9m9kSJK4yjf.4XAHojNRAJ/uRPAp1bIU7b.UOFAfeW2	\N	2026-05-12 10:49:22	2026-05-12 10:49:22	79430597604	interno	\N	\N	10011	t	f
198	HELIVAN TEIXEIRA MARIANO	helivan.tm@hotmail.com	\N	$2y$10$QcybxDrnvXd3xwTBTv8JxeHuFNdi2t5S.ylgS9fv2zWQjrtWjoUdC	\N	2026-05-12 10:49:22	2026-05-12 10:49:22	04133166612	interno	\N	\N	10012	f	f
199	WENDERSON PEREIRA DE FARIAS	wnderson@wnderson.com	\N	$2y$10$h5FmIiozJTkhXiZF6crU8eZL1GPQAwhqNGkvCaPGcpmxUih8Nf/sy	\N	2026-05-12 10:49:22	2026-05-12 10:49:22	06179084602	interno	\N	\N	10013	f	f
200	ANTONIO CARLOS DOS SANTOS	thecarllos@gmail.com	\N	$2y$10$bUmabX0LC0J5mT.VvCOxc.VfXdeV/ZkiNoVVBcSwUiCZOQoTNqf4O	\N	2026-05-12 10:49:22	2026-05-12 10:49:22	04047997609	interno	\N	\N	10014	f	f
201	MARIA LUIZA VALADARES SOUZA	marialuizavaladaresdesouza@gmailcom	\N	$2y$10$cQlIB49QwSOTbM7cDvguC.F1VK779hbQAHwXuDPnvCxh96adGeFKC	\N	2026-05-12 10:49:22	2026-05-12 10:49:22	02532023104	interno	\N	\N	10017	f	f
202	NAIRA LIMA MARCAL	nairamarcal@gmail.com	\N	$2y$10$bcz9bhTXAO3z7PTk1BEflurioMP.THJfrs/bXlCx0bW8CeAFMpPB2	\N	2026-05-12 10:49:22	2026-05-12 10:49:22	09014525648	interno	\N	\N	10018	t	f
203	DAGMAR CONCEIÇÃO SANTOS	dagsantos6@hotmail.com	\N	$2y$10$KW8.chyHNFwMthh/duW7ouc.0PdEMBGMCEUi69xyrulubZL/2rv7.	\N	2026-05-12 10:49:22	2026-05-12 10:49:22	86294830672	interno	\N	\N	10025	f	f
204	THAYS MOURA VALADARES	thaysmouravaladares@gmail.com	\N	$2y$10$vPYYCFC2cmF1pYmSputWOeSzNF/5jJL.Q8hKAcE35TOX0l6Dps35S	\N	2026-05-12 10:49:22	2026-05-12 10:49:22	10918321611	interno	\N	\N	10026	t	f
205	Paloma Chaves Almeida	paloma@gmil.com	\N	$2y$10$osQ7mFQpurMWDDghuMjvQ.EuUORH4Br/RFAJ3LqlI.o92ZEGwI8TG	\N	2026-05-12 10:49:22	2026-05-12 10:49:22	10854933697	interno	\N	\N	10028	t	f
206	Marcio Gustavo Alves Amaral	marciogustavoalves8@gmail.com	\N	$2y$10$0bfMeYMXPUV4M3pgPcYrnOO6w2hlemixouqskkp6fnfbb1afIh/s6	\N	2026-05-12 10:49:22	2026-05-12 10:49:22	02303767628	interno	\N	\N	10029	f	f
207	VALDINEY PEREIRA DA SILVA	zeney1072@hotmail.com	\N	$2y$10$rINWm.vQIp1Voh6zG1OCjerNbRqW./jT2t5FDmPwWJveoMyeIyADi	\N	2026-05-12 10:49:22	2026-05-12 10:49:22	83870008687	interno	\N	\N	10030	t	f
208	Vitor Ferreira Vaz	vitorvaz001@gmail.com	\N	$2y$10$azHV.CXAtnqsMM6FarvAiOpib.ofgRDJagst.j6cmS60hHXo8MF52	\N	2026-05-12 10:49:22	2026-05-12 10:49:22	02057622630	interno	\N	\N	10034	t	f
\.


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: ged_assinaturas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_assinaturas_id_seq', 83, true);


--
-- Name: ged_audit_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_audit_logs_id_seq', 39, true);


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

SELECT pg_catalog.setval('public.ged_documentos_id_seq', 53, true);


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

SELECT pg_catalog.setval('public.ged_notificacoes_id_seq', 39, true);


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

SELECT pg_catalog.setval('public.ged_solicitacoes_assinatura_id_seq', 49, true);


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

SELECT pg_catalog.setval('public.ged_versoes_id_seq', 51, true);


--
-- Name: ged_webhook_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ged_webhook_logs_id_seq', 40, true);


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

SELECT pg_catalog.setval('public.ug_organograma_id_seq', 4, true);


--
-- Name: ugs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ugs_id_seq', 2, true);


--
-- Name: user_ugs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.user_ugs_id_seq', 42, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.users_id_seq', 208, true);


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

\unrestrict Jn51BlCdnhtvwPTv12Q7KEk9CVM26XwRO5KNsDCI289T42gIV0axEWXBKw0KaNJ

