BenchPress
----------

TODO:
->Parser Class
->Ask EB about how BenchPress + Essence could help (i.e. she creates a design, that other person makes the html + css, etc.)
-wysiwyg
~Site relative links

Essence Base Theme TODO:
-Essence developer login
-Ability to upload html template -> dev.<domain>.wp-essence.com or dev.<domain>.<tld>
-Ability to publish dev -> live for first time (init)
-Ability to pull content + db from live -> dev (sync)
-Ability to commit theme changes on dev (commit)
-Ability to pull theme changes from dev to live (pull)
=Figure out best way to use drafts for content changes
-Dev site admin login only so client can't make content changes
-Way to easily edit theme in atom/etc. without local install

----------

1. Page Templates
Templates can be reused across multiple pages. To create a page template named "Template Name", add an id of template-template_name to the page's body tag. Every page must have a template selected.
If the body doesn't have an id, the page name will be used.

  Example 1:
    <body id="template-example_template"> ... </body>

  Example 2:
    example_template.html

2. Section templates
Sections can be reused on multiple pages. To create a section template named "Section Name", add an id of section-section_name to a div surrounding that section. All fields, images, menus, etc. must be in a section.

  Example:
    <div id="section-example_section"> ... </div>

3. Fields
Fields allow site admins to edit the page's content. To add a field named "Field Name", add a class of acf-field_name to the element.

Text elements:
These will allow the site admin to edit the text inside this field.
  -h1, h2, h3, h4, h5, h6, p, span

  Example:
    <h1 class="acf-example_title">Title Text</h1>

Link element:
This will allow the site admin to edit the href url and link title.
 -a

  Example:
    <a class="acf-example_link" href="https://google.com/">Link Text</a>

Image element:
This will allow the site admin to edit the image from the site media library.
  -img

  Example:
    <img class="acf-example_image" src="./img/example-image.jpg" />

4. Repeater
To repeat a set of elements in a repeater named "Repeater Name", wrap these elements in a div with class name acf-repeater_name each time they are repeated. The user will be able to add/remove these sections, as well as edit the internal fields.

  Example:
    <div class="acf-example_repeater">
      <img class="acf-profile_image" src="./img/alice.jpg" />
      <p class="acf-user_name">Alice</p>
    </div>
    <div class="acf-example_repeater">
      <img class="acf-profile_image" src="./img/bob.jpg" />
      <p class="acf-user_name">Bob</p>
    </div>
    ...

5. Menus
To create a menu named "Menu Name", add the class wp-menu_name to a ul element. Any li elements within this ul will then be editable, as well as any other sub ul menus nested in li elements.

  Example:
  <ul class="wp-menu_name">
    <li>
      <a>Text Only Menu Item</a>
    <li>
      <a href="https://google.com/">Menu Item</a>
    </li>
    <li>
      <a href="https://google.com">Nested Menu Item</a>
      <ul>
        <li ...
      </ul>
  </ul>

6. Theme Images
Images without an acf- prefixed tag will be imported into the theme.

  Example:
    <img src="./img/theme-image.png" />
