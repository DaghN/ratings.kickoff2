<?php
/**
 * Paths relative to site/public_html/ops/ (synced bundle — no repo root required on staging).
 */
declare(strict_types=1);

function k2_ops_root_directory(): string
{
    return dirname(__DIR__);
}

function k2_ops_milestones_seed_path(): string
{
    return k2_ops_root_directory() . '/data/milestones_definitions_seed.json';
}

function k2_ops_generalstatstable_sql_path(): string
{
    return k2_ops_root_directory() . '/sql/generalstatstable.sql';
}

function k2_ops_work_targets_ini_path(): string
{
    $opsIni = k2_ops_root_directory() . '/config/work-targets.ini';
    if (is_file($opsIni)) {
        return $opsIni;
    }

    $legacyIni = k2_ops_repo_root() . '/site/config/work-targets.ini';
    if (is_file($legacyIni)) {
        return $legacyIni;
    }

    return $opsIni;
}
