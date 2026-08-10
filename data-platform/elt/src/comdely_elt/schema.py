from __future__ import annotations

from dataclasses import dataclass
from enum import StrEnum

from sqlalchemy import (
    BigInteger,
    Boolean,
    Column,
    DateTime,
    ForeignKey,
    Integer,
    MetaData,
    Numeric,
    String,
    Table,
    Text,
)
from sqlalchemy.dialects.postgresql import JSONB, UUID


class LoadStrategy(StrEnum):
    UPSERT_SNAPSHOT = "upsert_snapshot"
    APPEND_ID = "append_id"


@dataclass(frozen=True, slots=True)
class TableSpec:
    source_name: str
    target: Table
    strategy: LoadStrategy

    @property
    def source_columns(self) -> tuple[str, ...]:
        return tuple(
            column.name
            for column in self.target.columns
            if column.name not in {"extracted_at", "etl_run_id"}
        )


metadata = MetaData()

etl_run = Table(
    "etl_run",
    metadata,
    Column("id", UUID(as_uuid=True), primary_key=True),
    Column("mode", String(20), nullable=False),
    Column("status", String(20), nullable=False),
    Column("source_system", String(100), nullable=False),
    Column("started_at", DateTime(timezone=True), nullable=False),
    Column("finished_at", DateTime(timezone=True)),
    Column("source_counts", JSONB),
    Column("target_counts", JSONB),
    Column("loaded_counts", JSONB),
    Column("error_message", Text),
    schema="meta",
)

etl_watermark = Table(
    "etl_watermark",
    metadata,
    Column("source_system", String(100), primary_key=True),
    Column("source_table", String(100), primary_key=True),
    Column("strategy", String(30), nullable=False),
    Column("watermark_column", String(100)),
    Column("watermark_value", BigInteger),
    Column("updated_at", DateTime(timezone=True), nullable=False),
    Column("etl_run_id", UUID(as_uuid=True), ForeignKey("meta.etl_run.id"), nullable=False),
    schema="meta",
)


def _audit_columns() -> tuple[Column, Column]:
    return (
        Column("extracted_at", DateTime(timezone=True), nullable=False),
        Column(
            "etl_run_id",
            UUID(as_uuid=True),
            ForeignKey("meta.etl_run.id"),
            nullable=False,
            index=True,
        ),
    )


raw_user = Table(
    "user",
    metadata,
    Column("id", BigInteger, primary_key=True),
    Column("nom", String(255), nullable=False),
    Column("prenom", String(255), nullable=False),
    Column("email", String(255), nullable=False),
    # The Symfony password hash is intentionally excluded from the DW.
    Column("role", String(255), nullable=False),
    Column("libelle", String(255)),
    Column("is_deleted", Boolean, nullable=False),
    Column("demo_batch", String(64)),
    *_audit_columns(),
    schema="raw",
)

raw_client = Table(
    "client",
    metadata,
    Column("id", BigInteger, primary_key=True),
    Column("fournisseur_id", BigInteger, nullable=False),
    Column("nom", String(255)),
    Column("prenom", String(255)),
    Column("societe", String(255)),
    Column("telephone", String(30)),
    Column("email", String(255)),
    Column("rue", Text),
    Column("complement_adresse", String(255)),
    Column("ville", String(150)),
    Column("code_postal", String(20)),
    Column("is_deleted", Boolean, nullable=False),
    Column("demo_batch", String(64)),
    *_audit_columns(),
    schema="raw",
)

raw_product = Table(
    "product",
    metadata,
    Column("id", BigInteger, primary_key=True),
    Column("libelle", String(255), nullable=False),
    Column("description", Text, nullable=False),
    Column("image", String(255)),
    Column("prix", Numeric(10, 3), nullable=False),
    Column("id_fournisseur_id", BigInteger, nullable=False),
    Column("is_deleted", Boolean),
    Column("demo_batch", String(64)),
    Column("created_at", DateTime(timezone=True)),
    *_audit_columns(),
    schema="raw",
)

raw_product_variation = Table(
    "product_variation",
    metadata,
    Column("id", BigInteger, primary_key=True),
    Column("product_id", BigInteger, nullable=False),
    Column("libelle", String(255), nullable=False),
    Column("attributs", JSONB, nullable=False),
    Column("prix_supplement", Numeric(10, 3), nullable=False),
    Column("stock", Integer, nullable=False),
    Column("stock_utilise", Integer, nullable=False),
    Column("stock_reserve", Integer, nullable=False),
    Column("reference", String(100)),
    Column("is_deleted", Boolean, nullable=False),
    *_audit_columns(),
    schema="raw",
)

raw_commande = Table(
    "commande",
    metadata,
    Column("id", BigInteger, primary_key=True),
    Column("numero", BigInteger, nullable=False),
    Column("date", DateTime(timezone=True), nullable=False),
    Column("total_ht", Numeric(10, 3), nullable=False),
    Column("taux_tva", Numeric(5, 3), nullable=False),
    Column("statut", String(30), nullable=False),
    Column("note", Text),
    Column("is_deleted", Boolean, nullable=False),
    Column("user_id", BigInteger, nullable=False),
    Column("fournisseur_id", BigInteger, nullable=False),
    Column("client_id", BigInteger),
    Column("demo_batch", String(64)),
    *_audit_columns(),
    schema="raw",
)

raw_ligne_commande = Table(
    "ligne_commande",
    metadata,
    Column("id", BigInteger, primary_key=True),
    Column("commande_id", BigInteger, nullable=False),
    Column("produit_id", BigInteger, nullable=False),
    Column("variation_id", BigInteger, nullable=False),
    Column("nom_produit", String(255), nullable=False),
    Column("nom_variation", String(255), nullable=False),
    Column("quantite", Integer, nullable=False),
    Column("prix_unitaire", Numeric(10, 3), nullable=False),
    *_audit_columns(),
    schema="raw",
)

raw_mouvement_stock = Table(
    "mouvement_stock",
    metadata,
    Column("id", BigInteger, primary_key=True),
    Column("variation_id", BigInteger, nullable=False),
    Column("type", String(40), nullable=False),
    Column("quantite", Integer, nullable=False),
    Column("stock_avant", Integer, nullable=False),
    Column("stock_apres", Integer, nullable=False),
    Column("stock_reserve_avant", Integer, nullable=False),
    Column("stock_reserve_apres", Integer, nullable=False),
    Column("commande_id", BigInteger),
    Column("created_by_id", BigInteger, nullable=False),
    Column("created_at", DateTime(timezone=True), nullable=False),
    Column("commentaire", Text),
    Column("demo_batch", String(64)),
    *_audit_columns(),
    schema="raw",
)

raw_historique_statut = Table(
    "historique_statut_commande",
    metadata,
    Column("id", BigInteger, primary_key=True),
    Column("commande_id", BigInteger, nullable=False),
    Column("ancien_statut", String(30)),
    Column("nouveau_statut", String(30), nullable=False),
    Column("changed_at", DateTime(timezone=True), nullable=False),
    Column("changed_by_id", BigInteger, nullable=False),
    *_audit_columns(),
    schema="raw",
)

raw_parametre = Table(
    "parametre",
    metadata,
    Column("id", BigInteger, primary_key=True),
    Column("numero_commande", BigInteger, nullable=False),
    Column("tva", Numeric(5, 3), nullable=False),
    *_audit_columns(),
    schema="raw",
)

raw_reclamation = Table(
    "reclamation",
    metadata,
    Column("id", BigInteger, primary_key=True),
    Column("fournisseur_id", BigInteger, nullable=False),
    Column("admin_assigne_id", BigInteger),
    Column("objet", String(255), nullable=False),
    Column("description", Text, nullable=False),
    Column("categorie", String(30), nullable=False),
    Column("priorite", String(20), nullable=False),
    Column("statut", String(40), nullable=False),
    Column("created_at", DateTime(timezone=True), nullable=False),
    Column("updated_at", DateTime(timezone=True), nullable=False),
    Column("resolved_at", DateTime(timezone=True)),
    *_audit_columns(),
    schema="raw",
)

raw_reclamation_message = Table(
    "reclamation_message",
    metadata,
    Column("id", BigInteger, primary_key=True),
    Column("reclamation_id", BigInteger, nullable=False),
    Column("auteur_id", BigInteger, nullable=False),
    Column("contenu", Text, nullable=False),
    Column("created_at", DateTime(timezone=True), nullable=False),
    *_audit_columns(),
    schema="raw",
)


TABLE_SPECS = (
    TableSpec("user", raw_user, LoadStrategy.UPSERT_SNAPSHOT),
    TableSpec("client", raw_client, LoadStrategy.UPSERT_SNAPSHOT),
    TableSpec("product", raw_product, LoadStrategy.UPSERT_SNAPSHOT),
    TableSpec("product_variation", raw_product_variation, LoadStrategy.UPSERT_SNAPSHOT),
    TableSpec("commande", raw_commande, LoadStrategy.UPSERT_SNAPSHOT),
    TableSpec("ligne_commande", raw_ligne_commande, LoadStrategy.UPSERT_SNAPSHOT),
    TableSpec("mouvement_stock", raw_mouvement_stock, LoadStrategy.APPEND_ID),
    TableSpec(
        "historique_statut_commande",
        raw_historique_statut,
        LoadStrategy.APPEND_ID,
    ),
    TableSpec("parametre", raw_parametre, LoadStrategy.UPSERT_SNAPSHOT),
    TableSpec("reclamation", raw_reclamation, LoadStrategy.UPSERT_SNAPSHOT),
    TableSpec(
        "reclamation_message",
        raw_reclamation_message,
        LoadStrategy.APPEND_ID,
    ),
)

TABLE_SPEC_BY_NAME = {spec.source_name: spec for spec in TABLE_SPECS}
