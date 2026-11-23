<?php

declare(strict_types=1);

namespace Magna\Docs\Settings;

use Magna\Settings\Settings;

class DocsSettings extends Settings
{
    /** Custom domain for the docs frontend (e.g. docs.example.com). Empty = serve at /docs. */
    public string $custom_domain = '';

    /** Branding name shown in the docs header. Falls back to the CMS site_name when empty. */
    public string $site_name = '';

    /** Path to the logo file (relative to the public disk). Falls back to the CMS logo. */
    public string $logo_path = '';

    /** Path to the favicon file. Falls back to the CMS favicon. */
    public string $favicon_path = '';

    /** Copyright / footer text shown on the left of the docs footer. Empty = hidden. */
    public string $copyright_text = '';

    /**
     * Whether the footer shows the "Made with Magna Docs" credit link. On by
     * default so existing sites look the same; a site that must not name the
     * software it runs on turns it off.
     */
    public bool $show_credit = true;

    /**
     * Role handles that are allowed to create / edit docs pages.
     * Empty = all admin roles can edit.
     *
     * @var string[]
     */
    public array $editor_roles = [];
}
