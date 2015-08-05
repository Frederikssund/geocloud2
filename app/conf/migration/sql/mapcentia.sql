-- Database: mapcentia

-- DROP DATABASE mapcentia;

CREATE DATABASE mapcentia
WITH OWNER = postgres
ENCODING = 'UTF8'
TABLESPACE = pg_default
LC_COLLATE = 'en_US.UTF-8'
LC_CTYPE = 'en_US.UTF-8'
CONNECTION LIMIT = -1;

-- Table: users

-- DROP TABLE users;

CREATE TABLE users
(
  screenname character varying(255),
  pw character varying(255),
  email character varying(255),
  zone character varying,
  parentdb character varying(255),
  created timestamp with time zone DEFAULT ('now'::text)::timestamp(0) with time zone,
  usergroup character varying(255)
)
