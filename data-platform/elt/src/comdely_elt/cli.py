from __future__ import annotations

import argparse
import json
import sys
from dataclasses import asdict
from typing import Sequence

from comdely_elt.config import Settings
from comdely_elt.pipeline import ELTPipeline, RunResult


def _serialize(result: RunResult) -> str:
    payload = asdict(result)
    payload["run_id"] = str(result.run_id)
    return json.dumps(payload, ensure_ascii=False, indent=2, sort_keys=True)


def execute(mode: str) -> int:
    try:
        settings = Settings.from_env()
        result = ELTPipeline(settings).run(mode)
    except Exception as error:
        print(f"ELT failed: {type(error).__name__}: {error}", file=sys.stderr)
        return 1
    print(_serialize(result))
    return 0


def main(argv: Sequence[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Load the Comdely MariaDB source into PostgreSQL raw tables."
    )
    parser.add_argument("mode", choices=("full", "incremental"))
    arguments = parser.parse_args(argv)
    return execute(arguments.mode)


def full_main() -> int:
    return execute("full")


def incremental_main() -> int:
    return execute("incremental")
