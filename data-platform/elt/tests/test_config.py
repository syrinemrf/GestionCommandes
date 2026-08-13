import pytest

from comdely_elt.config import ConfigurationError, Settings


def test_configuration_from_environment() -> None:
    settings = Settings.from_env(
        {
            "SOURCE_DATABASE_URL": (
                "mysql://reader:secret@localhost/source"
                "?serverVersion=10.4.32-MariaDB&charset=utf8mb4"
            ),
            "DW_DATABASE_URL": "postgresql://writer:secret@localhost/dw",
            "ELT_BATCH_SIZE": "250",
            "ELT_SOURCE_SYSTEM": "test_source",
            "ELT_SOURCE_TIMEZONE": "Africa/Tunis",
        }
    )

    assert settings.source_database_url.startswith("mysql+pymysql://")
    assert "serverVersion" not in settings.source_database_url
    assert "charset=utf8mb4" in settings.source_database_url
    assert settings.target_database_url.startswith("postgresql+psycopg://")
    assert settings.batch_size == 250
    assert settings.source_system == "test_source"


@pytest.mark.parametrize(
    ("overrides", "message"),
    [
        ({"SOURCE_DATABASE_URL": ""}, "SOURCE_DATABASE_URL"),
        ({"DW_DATABASE_URL": ""}, "DW_DATABASE_URL"),
        ({"ELT_BATCH_SIZE": "0"}, "greater than zero"),
        ({"ELT_BATCH_SIZE": "invalid"}, "must be an integer"),
        ({"ELT_SOURCE_TIMEZONE": "Invalid/Timezone"}, "Unknown"),
    ],
)
def test_invalid_configuration(overrides: dict[str, str], message: str) -> None:
    environment = {
        "SOURCE_DATABASE_URL": "mysql+pymysql://reader:secret@localhost/source",
        "DW_DATABASE_URL": "postgresql+psycopg://writer:secret@localhost/dw",
    }
    environment.update(overrides)

    with pytest.raises(ConfigurationError, match=message):
        Settings.from_env(environment)
