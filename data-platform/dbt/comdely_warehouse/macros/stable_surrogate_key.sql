{% macro stable_surrogate_key(columns) -%}
    md5(
        concat_ws(
            '|',
            {%- for column in columns %}
            coalesce(cast({{ column }} as text), '__dbt_null__')
            {%- if not loop.last %}, {% endif -%}
            {%- endfor %}
        )
    )
{%- endmacro %}
