/**
 * @file
 * Initialize's JavaScript for Profile and adds custom adjustments
 */

(function ($, Drupal) {
  Drupal.behaviors.academicProgramLoad = {
    attach: function (context, settings) {
      $('.sub-menu__back-link', context).attr('href', document.referrer);
      console.log('academic programs script is being loaded.');
    }
  };
})(jQuery, Drupal);
