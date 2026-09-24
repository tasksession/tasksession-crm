<?php
/**
 * Curated Google Fonts family names (no API). Used by theme-style Fonts tab.
 *
 * @return list<string>
 */
function google_fonts_curated_list() {
    return array(
        'Lato', 'Roboto', 'Open Sans', 'Montserrat', 'Poppins', 'Inter', 'Nunito', 'Raleway',
        'Ubuntu', 'Rubik', 'Work Sans', 'Noto Sans', 'Source Sans 3', 'DM Sans', 'Manrope',
        'Outfit', 'Plus Jakarta Sans', 'Figtree', 'Lexend', 'Mulish', 'Quicksand', 'Karla',
        'Barlow', 'Heebo', 'Titillium Web', 'Exo 2', 'Oxygen', 'Cabin', 'Asap', 'Dosis',
        'Varela Round', 'Jost', 'Be Vietnam Pro', 'Red Hat Display', 'IBM Plex Sans',
        'Fira Sans', 'PT Sans', 'Mukta', 'Hind', 'Signika', 'Catamaran', 'Kanit', 'Prompt',
        'Sarabun', 'Mada', 'Almarai', 'Chivo', 'Overpass', 'Public Sans', 'Sora', 'Urbanist',
        'Space Grotesk', 'Syne', 'Epilogue', 'Commissioner', 'Recursive', 'Atkinson Hyperlegible',
        'Atma', 'B612', 'BioRhyme', 'Bitter', 'Brygada 1918', 'Crimson Pro', 'Domine',
        'EB Garamond', 'Fraunces', 'Gelasio', 'IBM Plex Serif', 'Libre Baskerville',
        'Lora', 'Merriweather', 'Noto Serif', 'Playfair Display', 'PT Serif', 'Roboto Slab',
        'Source Serif 4', 'Spectral', 'Zilla Slab', 'Abril Fatface', 'Alfa Slab One',
        'Anton', 'Archivo Black', 'Bebas Neue', 'Black Ops One', 'Bungee', 'Creepster',
        'Fredoka', 'Lilita One', 'Lobster', 'Pacifico', 'Permanent Marker', 'Righteous',
        'Russo One', 'Staatliches', 'Teko', 'Yellowtail',
    );
}

/**
 * @return array<string, string> label => CSS font-family stack (no quotes on multi-word first family handled in CSS)
 */
function theme_fonts_websafe_presets() {
    return array(
        'System UI' => 'system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif',
        'Georgia, serif' => 'Georgia, serif',
        'Palatino / Book Antiqua' => 'Palatino Linotype, Book Antiqua, Palatino, serif',
        'Times New Roman' => 'Times New Roman, Times, serif',
        'Arial, Helvetica' => 'Arial, Helvetica, sans-serif',
        'Verdana, Geneva' => 'Verdana, Geneva, sans-serif',
        'Trebuchet MS' => 'Trebuchet MS, Helvetica, sans-serif',
        'Courier New' => 'Courier New, Courier, monospace',
    );
}

/**
 * Stable keys for form values (websafe:key).
 *
 * @return array<string, array{label:string, stack:string}>
 */
function theme_fonts_websafe_presets_by_key() {
    return array(
        'system_ui' => array(
            'label' => 'System UI',
            'stack' => 'system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif',
        ),
        'georgia_serif' => array('label' => 'Georgia, serif', 'stack' => 'Georgia, serif'),
        'palatino' => array(
            'label' => 'Palatino / Book Antiqua',
            'stack' => 'Palatino Linotype, Book Antiqua, Palatino, serif',
        ),
        'times_new_roman' => array('label' => 'Times New Roman', 'stack' => 'Times New Roman, Times, serif'),
        'arial_helvetica' => array('label' => 'Arial, Helvetica', 'stack' => 'Arial, Helvetica, sans-serif'),
        'verdana_geneva' => array('label' => 'Verdana, Geneva', 'stack' => 'Verdana, Geneva, sans-serif'),
        'trebuchet' => array('label' => 'Trebuchet MS', 'stack' => 'Trebuchet MS, Helvetica, sans-serif'),
        'courier_new' => array('label' => 'Courier New', 'stack' => 'Courier New, Courier, monospace'),
    );
}
