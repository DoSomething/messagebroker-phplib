'use strict';
/**
 * Task runner for unit tests
 *
 * How To:
 *
 * Using Gulp.js to check your code quality
 * https://marcofranssen.nl/using-gulp-js-to-check-your-code-quality/
 *
 * Automate Your Tasks Easily with Gulp.js
 * https://scotch.io/tutorials/automate-your-tasks-easily-with-gulp-js
 */

var gulp    = require('gulp');
var phplint = require('phplint').lint;
var phpunit = require('gulp-phpunit');
var phpcs   = require('gulp-phpcs');
var notify  = require('gulp-notify');
var shell   = require('gulp-shell');

/**
 * phplint: http://www.icosaedro.it/phplint/
 *
 * PHPLint is a validator and documentator for PHP 5 and PHP 7 programs. PHPLint
 * extends the PHP language through transparent meta-code that can drive the parser
 * to a even more strict check of the source. PHPLint is not simply a checker: it
 * implements a new, strong typed, language built over the PHP language. You can
 * build your programs from scratch with PHPLint in mind, or you can check and fix
 * existing programs, or you can follow the quick-and-dirty PHP programming way and
 * then add the PHPLint meta-code later once the program is finished. Whatever is
 * the strategy you choose, PHPLint makes your programs safer, more secure, well
 * documented and with drastically less bugs.
 */
gulp.task('phplint', function (cb) {
  phplint(['./**/*.php', '!./node_modules/**/*', '!./vendor/**/*'],  { limit: 10 }, function (err, stdout, stderr) {
    if (err) {
      cb(err);
      process.exit(1);
    }
    cb();
  });
});

/**
 * phpunit: https://phpunit.de/index.html
 *
 * PHPUnit is a programmer-oriented testing framework for PHP. It is an instance of
 * the xUnit architecture for unit testing frameworks.
 *
 * Installed with Composer: https://phpunit.de/manual/current/en/installation.html#installation.composer
 */
gulp.task('phpunit', function () {
  var options = {debug: false, notify: true};
  gulp.src('phpunit.xml')
    .pipe(phpunit('vendor/bin/phpunit --verbose tests', options))
    .on('error', notify.onError({
      title: "Failed Tests!",
      message: "Error(s) occurred during testing..."
    }));
});

/**
 * phpcs & phpcbf (PHP_CodeSniffer): https://github.com/squizlabs/PHP_CodeSniffer
 *
 * PHP_CodeSniffer is a set of two PHP scripts; the main phpcs script that tokenizes
 * PHP, JavaScript and CSS files to detect violations of a defined coding standard,
 * and a second phpcbf script to automatically correct coding standard violations.
 * PHP_CodeSniffer is an essential development tool that ensures your code remains
 * clean and consistent.
 */
gulp.task('phpcs', function () {
  return gulp.src(['./**/*.php', './**/*.inc', 'bin', '!./messagebroker-config/**/*', '!./node_modules/', '!./vendor/**/*'])
    .pipe(phpcs({
      bin: 'vendor/bin/phpcs',
      standard: 'ruleset.xml',
      warningSeverity: 0,
      showSniffCode: 1,
      colors: 1
    }))
    .pipe(phpcs.reporter('log'));
});

gulp.task('phpcbf', shell.task(['vendor/bin/phpcbf --standard=PSR2 --ignore=vendor/,node_modules/,messagebroker-config src']));

/**
 * watch (Gulp): https://github.com/gulpjs/gulp
 *
 * Watch functionality built in to base package. See entry in "default" command.
 */
gulp.task('watch', function () {
  gulp.watch(['composer.json', 'phpunit.xml', './**/*.php', './**/*.inc', '!./messagebroker-config/**/*', '!./vendor/**/*', '!./node_modules/**/*'],
    function (event) {
      console.log('File ' + event.path + ' was ' + event.type + ', running tasks...');
    });
  gulp.watch('composer.json', ['dump-autoload']);
  gulp.watch(['phpunit.xml', './**/*.php', './**/*.inc', '!messagebroker-config/', '!vendor/**/*', '!node_modules/**/*'], ['phplint', 'phpunit']);
});


// Configured gulp commands
gulp.task('default', ['phplint', 'phpcs', 'phpunit']);
gulp.task('test', ['phplint', 'phpunit']);
gulp.task('lint', ['phpcs', 'phplint']);
gulp.task('auto-lint', ['phpcbf', 'phpcs', 'phplint']);
