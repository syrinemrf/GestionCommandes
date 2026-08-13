from datetime import date

import pytest

from comdely_ml.config import ConfigurationError, Settings


def test_configuration_normalizes_symfony_postgresql_url() -> None:
    settings = Settings.from_env(
        {
            'DW_DATABASE_URL': (
                'postgresql://reader:secret@localhost:5433/comdely_dw'
                '?serverVersion=16&charset=utf8'
            ),
            'ML_DATASET_START': '2024-08-04',
            'ML_DATASET_END': '2026-08-04',
        }
    )

    assert settings.database_url == (
        'postgresql+psycopg://reader:secret@localhost:5433/comdely_dw'
    )
    assert settings.dataset_start == date(2024, 8, 4)
    assert settings.dataset_end == date(2026, 8, 4)


def test_configuration_requires_database_url() -> None:
    with pytest.raises(ConfigurationError, match='DW_DATABASE_URL'):
        Settings.from_env({})


def test_configuration_rejects_future_dataset_end() -> None:
    with pytest.raises(ConfigurationError, match='future'):
        Settings.from_env(
            {
                'DW_DATABASE_URL': (
                    'postgresql+psycopg://reader:secret@localhost/comdely_dw'
                ),
                'ML_DATASET_END': '2999-01-01',
            }
        )
