<?php
/**
 * The Prairie Dispatch — founding-site pack.
 *
 * The founding paper predates launch packs and self-provisioned everything;
 * this pack exists only to declare its public hostnames, so the seeder can
 * write its domains rows like any other paper's. It seeds no desks, no
 * settings, no sources and no stories, and is safe to run any number of
 * times.
 */

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['prairiedispatch.ca', 'www.prairiedispatch.ca'],

    /* The one setting the founding paper was missing: the byline a Hermes
       filing carries. The seeder writes settings only where absent, so the
       newsroom's own edits are never overwritten. */
    'settings' => [
        'automated_byline' => 'Dispatch Newsroom',
    ],
];
