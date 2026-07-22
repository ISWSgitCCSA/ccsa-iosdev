CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Included Add-on Modules
 * Users and Registrations in Views
 * Multilingual Considerations
 * Maintainers


INTRODUCTION
------------

Registration is a flexible module that allows tracking user registrations for events, or just about anything you want people to sign up for. Registration can be integrated with Drupal Commerce to allow fee-based registrations: sell tickets to your stuff! This module is also handy if you want to collect information along with registrations: like shoe-size for a bowling event.

Registration does lots of things you are expecting from a registration system (like allowing you to restrict the number of total registrations for a given event), and other stuff you are crossing your fingers and hoping for (like building in automated reminder messages for your registrants).

If you want to sell registrations to your events, use the [Commerce Registration](https://www.drupal.org/project/commerce_registration) module to integrate Registration with Drupal Commerce.

 * For a full description of this module visit the [Registration project page](https://www.drupal.org/project/registration).

 * To submit bug reports and feature suggestions, or to track changes, visit the [Registration project issues queue](https://www.drupal.org/project/issues/registration).


REQUIREMENTS
------------

This module requires no modules outside of Drupal core. Upon installation it enables the following core modules if they are not enabled yet:

* Datetime
* Field
* Text
* User
* Workflows


INSTALLATION
------------

Install the Registration module as you would normally install a contributed
Drupal module. Visit [https://www.drupal.org/node/1897420](https://www.drupal.org/node/1897420) for further
information.

Once the module is installed, a new **Registration** field type becomes available in Field UI.


CONFIGURATION
-------------

See the [step-by-step documentation guide](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/entity-registration/registration-for-site-builders/step-by-step) for instructions on how to configure the Registration module.


INCLUDED ADD-ON MODULES
-----------------------

The Registration module includes the following submodules that can be enabled to provide additional functionality.

1. Registration Administrative Overrides - allow system administrators to override limits within registration settings.
1. Registration Cancel By - adds a "cancel by" date to host entity registration settings.
1. Registration Change Host - allow the host entity for an existing registration to be changed.
1. Registration Confirmation - send confirmation emails when registrations are completed.
1. Registration Inline Entity Form - allow registration settings to be edited on the host entity edit form.
1. Registration Purger - automatically delete registrations and registration settings for a host entity that is deleted.
1. Registration Scheduled Action - setup scheduled emails based on registration settings dates in your system.
1. Registration Wait List - allow overflow registrations to a wait list.
1. Registration Workflow - add permissions and operations for workflow transitions and integrate with [ECA Workflow](https://www.drupal.org/project/eca) transitions.

See the [submodule documentation](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/entity-registration/registration-submodules) in online help for more information.


USERS AND REGISTRATIONS IN VIEWS
--------------------------------
If you have the Views module installed, and want to create an administrative listing of Users and their associated registrations, the reverse relationship from a User entity to their registrations is not available using the Registration module "out of the box", due to core issue #2706431. To enable this relationship, install the [Entity API](https://www.drupal.org/project/entity) contributed module. Then use the **Registration using user_uid** relationship (with description *Relate each Registration with a user_uid field set to the user*) to associate Users to their registrations. The relationship named **User registration** (with description *Relate users to their registrations*) is for the unlikely case that the User entity is a host entity for registrations, and is not suitable for this type of listing.


MULTILINGUAL CONSIDERATIONS
---------------------------

This version includes full support for multilingual sites. Although registrations are not translatable entities, each registration has a language field indicating the language used to create it. This allows you to craft different reminder emails for each language and target just the registrants for a given language.

Registration settings can also vary per language. In many cases, untranslatable settings fields, such as the capacity for a given registration event, should be the same across all language variants. Visit the global settings page at /admin/structure/registration-settings to control how registration settings are handled across languages. This feature is only visible to sites with multiple languages installed.

Note that some listings, such as the Registration Summary at /admin/people/registration-summary, only display registrations in the current interface language by default. This may not be appropriate for every multilingual site. You can customize the listings as needed for your use case.


MAINTAINERS
-----------

 * John Oltman - [https://www.drupal.org/u/johnoltman](https://www.drupal.org/u/johnoltman)
 * Martin Anderson-Clutz - [https://www.drupal.org/u/mandclu](https://www.drupal.org/u/mandclu)
 * Lev Tsypin (levelos) - [https://www.drupal.org/u/levelos](https://www.drupal.org/u/levelos)
