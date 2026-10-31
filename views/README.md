# View hierarchy

Group templates by feature and job. Keep page templates in their feature folder and feature-only fragments in its partials/ folder.

    views/
      layouts/
        app.php
        partials/ (head, header, sidebar, flash_messages, footer)
      auth/login.php
      errors/error.php
      dashboard/index.php
      account/index.php
      voters/
        index.php
        form.php
      members/
        index.php
        show.php
        edit.php
        enroll.php
        partials/ (identity, overview, background, management, edit_form,
                   identifier_fields, background_fields, filters)
      locations/
        electoral/
          index.php
          partials/ (filters, table, editor)
        residential/
          regions/index.php
          districts/index.php
          towns/index.php
          partials/directory.php
      uploads/
        voters/index.php
        polling_stations/index.php
        review.php
        partials/pending.php
      shared/
        forms/ (person, photo, residential_address, electoral_location)
        people/
          directory.php
          partials/ (filters, table)
        components/directory/ (metrics, pagination, filter_chips)

## Adding templates

Render a feature-relative name without the extension:

    View::render('members/show', $data, 'Member profile');

Include partials using the same resolver, preserving local variable scope:

    require \App\View::path('members/partials/identity');

Do not use chains of ../, accept view filenames from request parameters, or add loose templates at the views root. View::path validates template names and checks that the file exists. These are internal paths; public page names/URLs retain their existing values.

Use index.php for lists/landing pages, show.php for profiles, edit.php for dedicated editors, and descriptive workflow names such as enroll.php and review.php. Keep fragments in their owning feature unless they are genuinely reused. Cross-feature forms and components belong in shared/. Voter/member index wrappers intentionally share the people directory instead of duplicating markup.

Layouts provide the frame; feature templates provide page content. Keep queries, validation and writes in the existing service/model/controller layers. Login and errors remain standalone pages.

Historical copy templates are preserved privately under storage/archives/views/20261030-layout-refactor/. They are not rendered. Do not place backup/copy files among active views.

Future work should also use feature/job folders within the existing MVC/asset roots. Update explicit autoload entries and references for new nested classes; retain established migration ordering and necessary entry/configuration conventions.
