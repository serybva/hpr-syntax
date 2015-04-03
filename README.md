=== HprSyntax ===
Contributors: Sebastien Vray (www.serybva.com)
Tags: syntax highlighting, syntax, highlight, code, formatting, code, CSS, html, php, sourcecode, cached, performance
Requires at least: 3.5
Tested up to: 3.5.1
Stable tag: 1.0

HprSyntax a high performance syntax highlighter based on WP-Syntax/Geshi

== Description ==
HprSyntax is a syntax highlighting plugin based on WP-Syntax wordpress plugin using Geshi

It works slightly different, while WP-Syntax highlights each code snippet wrapped with pre-tag on EACH
post display, HprSyntax highlights them on each post creation/update avoiding performance issues for visitors.

It's like a permanent cache, each time you create/update a post HprSyntax parses the post content looking for
pre tags, once a pre tag is matches HprSyntax generates the HTML code for syntax highlighting,
stores it in the database, and finally gives an id attribute to the pre tag under the form id="hpr-syntax-x"
where x is the id of the corresponding generated HTML code in the database, thus the code snippet remains easily editable.
When the post is accessed HprSyntax replaces each pre tag with it's corresponding HTML code which is much faster than
generating the HTML highlighting code on each post display (about 0.2 ms vs at least 200ms using WP-Syntax for each snippet).

Why WP-Syntax can be so slow?
Because it is based on Geshi:
Each time Geshi parses a code snippet it optimizes it's regexes which takes a least 200ms, if you parse
a code snippet of 2 lines with Geshi it will take about 250 ms, something like 300ms for 500 lines of code.

Imagine now you put 10 code snippets of 2 lines in a post, Geshi is called 10 times and it takes at least 2.5 sec to display
the post, probably (a lot?) more if your website has some visitors at that moment, no one can afford this on the 3rd second
your visitors are gone.

Here's the results of a quick benchmark I ran:
Content type | Overall page generation time (HprSyntax) | Overall page generation time (WP-Syntax)
_____________|__________________________________________|_________________________________________
460 long     |                                          |
code snippet |	Min: 520ms - Max: 580ms                 | Min: 980ms - Max: 1200ms
_____________|__________________________________________|_________________________________________
             |                                          |
1 line long  |  Min: 520ms - Max: 580ms                 | Min: 750ms - Max: 940ms
code snippet |                                          |
_____________|__________________________________________|_________________________________________
             |                                          |
Two 1 line   |  Min: 520ms - Max: 580ms                 | Min: 540ms - Max: 640ms
long code    |                                          |
snippets     |                                          |
_____________|__________________________________________|_________________________________________
             |                                          |
2 code       |  Min: 520ms - Max: 580ms                 | Min: 760ms - Max: 850ms
snippets     |                                          |
1 line long  |                                          |
and 22 lines |                                          |
long         |                                          |
_____________|__________________________________________|_________________________________________
             |                                          |
13 code      |  Min: 580ms - Max: 650ms                 | Min: 3610ms - Max: 4070ms
snippets from|                                          |
1 to 2 lines |                                          |
long each    |                                          |
_____________|__________________________________________|_________________________________________

Those results are INDICATIVES and may vary from a environement to another depending of:
-Your wordpress version
-Your php version
-Your server (resources, hosting type, traffic etc)
-The other plugins (I measured the overall generation time for a page not the generation time of the HTML for snippets highlighting)

but they can give you a idea of how much HprSyntax can speed up your wordpress compared to WP-Syntax.


Additional functionalities:
Besides this basic performance optimization I added some functionalities:
-Admin panel for HprSyntax setting/maintenance operations
-A metabox on the post creation/edition page which allows you to disable HprSyntax for the corresponding post (enabled by default)

Note that unlike WP-Syntax HprSyntax assumes that the content of each snippet is escaped with html entities
(like &gt; instead of >) so the attribute "escaped" on pre tag is ignored.

= Basic Usage =

Wrap code blocks with `<pre lang="LANGUAGE" line="1">` and `</pre>` where **"LANGUAGE"**
is a [GeSHi](http://qbnz.com/highlighter/) supported language syntax.
The `line` attribute is optional. [More usage examples](http://wordpress.org/extend/plugins/wp-syntax/other_notes/)

== Installation ==

1. Upload hpr-syntax.zip to your Wordpress plugins directory, usually `wp-content/plugins/` and unzip the file.  It will create a `wp-content/plugins/wp-syntax/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Create a post/page that contains a code snippet following the [proper usage syntax](http://wordpress.org/extend/plugins/wp-syntax/other_notes/).

== Frequently Asked Questions ==

=First of all=
See the WP-Syntax FAQ http://wordpress.org/extend/plugins/wp-syntax/faq/ you might find some answers there

=How to disable HprSyntax on a specific post?=
Uncheck the 'Enable HprSyntax for this post' before you publish the post.

=What are dead references?=
Let's say you post a code snippet, HprSyntax will parse it using Geshi then it stores the output in the database.
Later you delete that code snippet from the post, HprSyntax will NOT delete the corresponding Geshi output previously
stored in the database, you've just created a dead reference.

=How do I get my database rid of dead references?=
Go to the backend, then Settings->HprSyntax click the 'Clean' button at the bottom of the page, wait, it's done.
By default HprSyntax does this automatically once a week, you can change this setting in the admin panel.

=How to remove inline styles and use CSS classes instead?=
Settings->HprSyntax check the 'Use css classes for highlighting instead of inline styles' checkbox, save the changes, you're done.

=I changed some settings but the changes didn't apply, what I did wrong?=
This is because you didn't reprocess, as the code highlighting is pre-generated and stored to the database you need to regenerate
all the HTML code for highlighting each time you changes a setting in the HprSyntax admin panel.
Click the unprocess button, then click the process button and it's done.
You can also check the 'Reprocess on each setting change?' checkbox so HprSyntax will automatically reprocess all the posts
each time you save changes to HrpSyntax settings, except those on which you explicitly disabled HprSyntax, be careful this can be a
very slow operation.

=What if I change the number of a pre tag id added by HprSyntax?=
If the new number correspond to a Geshi output id in the database the code snippet will be replaced on the post display
by another code snippet whichever comes first.
If the new number don't matches any id in the database HprSyntax will create it, the former Geshi output will not be deleted (see dead references).

=How do I remove the code highlighting from a specific post?=
Edit the post, uncheck the 'Enable HprSyntax for this post' and update the post.

=How to enable HprSyntax for pages or posts only?=
Go the to the HprSyntax settings panel, select the post types you want HprSyntax to be enabled on, save the changes.

=How to override default HprSyntax CSS style?=
Copy the default CSS file (wp-content/plugins/hpr-syntax/css/hpr-syntax.css) to your theme directory, then modify it.
Or just create a hpr-syntax.css file into your theme directory.

== Original WP-Syntax readme below ==

== Screenshots ==

1. PHP, no line numbers.
2. Java, with line numbers.
3. Ruby, with line numbers starting at 18.

== Usage ==

Wrap code blocks with `<pre lang="LANGUAGE" line="1">` and `</pre>` where **"LANGUAGE"** is a [GeSHi](http://qbnz.com/highlighter/) supported
language syntax. See below for a full list of supported languages.
The `line` attribute is optional.

**Example 1: PHP, no line numbers**

    <pre lang="php">
    <div id="foo">
    <?php
      function foo() {
        echo "Hello World!\\n";
      }
    ?>
    </div>
    </pre>


**Example 2: Java, with line numbers**

    <pre lang="java" line="1">
    public class Hello {
      public static void main(String[] args) {
        System.out.println("Hello World!");
      }
    }
    </pre>

**Example 3: Ruby, with line numbers starting at 18**

    <pre lang="ruby" line="18">
    class Example
      def example(arg1)
        return "Hello: " + arg1.to_s
      end
    end
    </pre>

**Example 4: If your code already has html entities escaped, use `escaped="true"` as an option**

    <pre lang="xml" escaped="true">
    &lt;xml&gt;Hello&lt;/xml&gt;
    </pre>

**Example 5: PHP, with line numbers and highlighting a specific line**

    <pre lang="php" line="1" highlight="3">
    <div id="foo">
    <?php
      function foo() {
        echo "Hello World!\\n";
      }
    ?>
    </div>
    </pre>

**Example 6: PHP, with a caption (file and/or file path of the source file)**

    <pre lang="php" src"https://github.com/shazahm1/Connections/blob/master/connections.php">
    <div id="foo">
    <?php
      function foo() {
        echo "Hello World!\\n";
      }
    ?>
    </div>
    </pre>

== Supported Languages ==

The following languages are most supported in the `lang` attribute:

abap, actionscript, actionscript3, ada, apache, applescript, apt_sources, asm,
**asp**, autoit, avisynth, **bash**, bf, bibtex, blitzbasic, bnf, boo, **c**,
c_mac, caddcl, cadlisp, cil, cfdg, cfm, cmake, cobol, cpp-qt, **cpp**,
**csharp**, **css**, d, dcs, delphi, diff, div, dos, dot, eiffel, email, erlang,
fo, fortran, freebasic, genero, gettext, glsl, gml, bnuplot, groovy, haskell,
hq9plus, **html4strict**, idl, ini, inno, intercal, io, **java**, **java5**,
**javascript**, kixtart, klonec, klonecpp, latex, **lisp**, locobasic, lolcode
lotusformulas, lotusscript, lscript, lsl2, lua, m68k, make, matlab, mirc,
modula3, mpasm, mxml, **mysql**, nsis, oberon2, **objc**, ocaml-brief, ocaml,
oobas, **oracle11**, oracle8, pascal, per, pic16, pixelbender, **perl**,
php-brief, **php**, plsql, povray, powershell, progress, prolog, properties,
providex, **python**, qbasic, **rails**, rebol, reg, robots, **ruby**, sas,
scala, scheme, scilab, sdlbasic, smalltalk, smarty, **sql**, tcl, teraterm,
text, thinbasic, tsql, typoscript, **vb**, **vbnet**, verilog, vhdl, vim,
visualfoxpro, visualprolog, whitespace, whois, winbatch, **xml**, xorg_conf,
xpp, z80

See the [GeSHi Documentation](http://qbnz.com/highlighter/geshi-doc.html)
for a full list of supported languages.

(Bold languages just highlight the more popular ones.)

== Styling Guidelines ==

WP-Syntax colors code using the default GeSHi colors.  It also uses inline
styling to make sure that code highlights still work in RSS feeds.  It uses a
default `wp-syntax.css` stylesheet for basic layout.  To customize your styling,
copy the default `wp-content/plugins/wp-syntax/wp-syntax.css` to your theme's
template directory and modify it.  If a file named `wp-syntax.css` exists in
your theme's template directory, this stylesheet is used instead of the default.
This allows theme authors to add their own customizations as they see fit.

== Advanced Customization ==

WP-Syntax supports a `wp_syntax_init_geshi` action hook to customize GeSHi
initialization settings.  Blog owners can handle the hook in a hand-made plugin
or somewhere else like this:

    <?php
    add_action('wp_syntax_init_geshi', 'my_custom_geshi_styles');

    function my_custom_geshi_styles(&$geshi)
    {
        $geshi->set_brackets_style('color: #000;');
        $geshi->set_keyword_group_style(1, 'color: #22f;');
    }
    ?>

This allows for a great possibility of different customizations. Be sure to
review the [GeSHi Documentation](http://qbnz.com/highlighter/geshi-doc.html).

== Changelog ==

= 1.0 02/09/2013 =
* NEW: CSS3 for alternating background lines for easier reading.
* OTHER: Completely refactor code to utilize current best practices for plugin development which will provide a solid foundation for further development.
* OTHER: Remove GeSHi contrib and test folders.
* OTHER: Move CSS to `css` subfolder.
* OTHER: Move JavaScript to `js` subfolder.
* OTHER: CSS fixes to keep theme from breaking output formatting.

= 0.9.13 09/01/12 =
* FEATURE: Added a new "src" shortcode option to allow reference of the source filename. Props: Steffen Vogel
* BUG: Properly enqueue the CSS file.
* BUG: Updated TinyMCE whitelist to allows required tags. Props: Steffen Vogel
* OTHER: Updated GeSHi to 1.0.8.11
* OTHER: Remove old unused code.
* OTHER: Imporved line highlighting. Props: Steffen Vogel
* OTHER: Added some additional CSS styles to help keep theme's from breaking the presentation of the code.

**0.9.12** : Fixed a range bug in the new highlight feature.

**0.9.11** : Added line highlighting support. User submitted patch. [Thanks Flynsarmy && Chimo](http://www.flynsarmy.com/2011/06/how-to-add-line-highlight-support-to-wp-syntax/)

**0.9.10** : Fix for security vulnerability when register_globals in php is enabled.

**0.9.9** : Fix to support child theme's. WP-Syntax now requires WP >= 3.0.
  Credit to [OddOneOut](http://wordpress.org/support/topic/wp-syntax-css-with-twenty-ten-child-theme)
  Updated to use 1.0.8.9.

**0.9.8** : Fix for optional line attributes; Tested on WP 2.8

**0.9.7** : Reverted GeSHi v1.0.8.3 to avoid a slew of issues;

**0.9.6** : Updated to use GeSHi v1.0.8.4;

**0.9.5** : Minor style override to prevent themes from mangling code structure

**0.9.4** : Updated to use GeSHi v1.0.8.3;

**0.9.3** : Fixed hard-coded plugin path
  ([#964](http://plugins.trac.wordpress.org/ticket/964));

**0.9.2** : Updated to use GeSHi v1.0.8.2; Added optional `escaped="true"`
  support in case code snippets are already escaped with html entities.

**0.9.1** : Updated to use GeSHi v1.0.8; Improved the FAQ;

**0.9** : Added support for anonymous subscribers to use pre tags in their
  comments allowing for their own colored code snippets [Fernando Briano];

**0.8** : Updated to use GeSHi v1.0.7.22 (this normally would be a revision
  release, but colors changed and there are 9 new languages supported); Added a
  font-size setting in the default css to thwart complaints about small sizes
  caused by other default WP themes;

**0.7** : Automaticaly included common styles without requiring manual theme
  customization [Christian Heim]; Added support for adding a custom
  wp-syntax.css stylesheet to a theme;

**0.6.1** : Updated to use GeSHi v1.0.7.21; Updated the WP compatibility version;

**0.6** : Support init hook for geshi settings (`wp_syntax_init_geshi`);
  ([#667](http://dev.wp-plugins.org/ticket/667))
  [[reedom](http://wordpress.org/support/topic/125127?replies=1#post-586215)]

**0.5.4** : Updated to use GeSHi v1.0.7.20;

**0.5.3** : Fixed styling guideline issue that affected IE 6 [kimuraco];

**0.5.2** : Updated to use GeSHi v1.0.7.19;

**0.5.1** : Switched `geshi` directory export to utilize
  [piston](http://piston.rubyforge.org/) instead of `svn:externals` properties;

**0.5** : Added support for single quoted attributes;
  ([#624](http://dev.wp-plugins.org/ticket/624))

**0.4** : Cleanup and documentation for WordPress.org [plugin
  listings](http://wordpress.org/extend/plugins/);

**0.3** : First official public release; Added line number support; Uses GeSHi v1.0.7.18;
([#532](http://dev.wp-plugins.org/ticket/532))

**0.2** : Internal release; Adds "before and after" filter support to avoid
conflicts with other plugins;
([#531](http://dev.wp-plugins.org/ticket/531))

**0.1** : First internal release; Uses GeSHi v1.0.7.16;

== Upgrade Notice ==

= 0.9.10 =
Fixes a security vulnerability. Upgrade immediately.

= 0.9.9 =
Compatible with WP >= 3.0 and latest GeSHi
