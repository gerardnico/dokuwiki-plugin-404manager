# Changes List

## 2016-06-26
  * If the user is a Writer and their is a redirection, a redirection doesn't occur anymore. The writer is redirected in `edit mode` and can create directly the page.
  * The redirection is a real redirection using 3XX HTTP status code in the same way that the plugin [redirect](https://www.dokuwiki.org/plugin:redirect).
  * When a intern redirection occurs the message is kept in the cookie in order to still show it when the new page is rendered.
  * Css declarations are now encapsulated around the class `redirect_manager` to avoid any style problem with other part of DokuWiki. Unfortunately, it is not possible to use the plugin base `404manager` as class name because it begins with a number and it's forbidden by Css.
  * The signature of the message "This message was fired by" was resolved. It was badly positioned and add no more the name of the plugin (because of the change in the plugin info method).
  * Unit test were added with the Dokuwiki Test Framework. See the [_test](_test) directory and the [DokuWiki Page](https://www.dokuwiki.org/devel:unittesting)


## 2015-11-03
  * Original version

