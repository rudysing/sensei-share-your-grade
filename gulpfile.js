const { dest, parallel, series, src } = require( 'gulp' );
const del = require( 'del' );
const sass = require( 'gulp-sass' );
const wpPot = require( 'gulp-wp-pot' );
const zip = require( 'gulp-zip' );

const buildDir = 'build/sensei-share-your-grade';

function clean() {
	return del( [ 'build' ] );
}

function css() {
	return src( 'assets/css/*.scss')
		.pipe( sass() )
		.pipe( sass( { outputStyle: 'expanded' } ) )
		.pipe( dest( 'assets/css' ) )
		.pipe( dest( buildDir + '/assets/css' ) )
}

function cssMinify() {
	return src( 'assets/css/*.scss')
		.pipe( sass( { outputStyle: 'compressed' } ) )
		.pipe( dest( buildDir + '/assets/css' ) )
}

function docs() {
	return src( [ 'changelog.txt', 'README.md' ] )
		.pipe( dest( buildDir ) )
}

function languages() {
	return src( 'languages/*.*', { base: '.' } )
		.pipe( dest( buildDir ) );
}

function php() {
	return src( [ 'sensei-share-your-grade.php', 'includes/**/*.php' ], { base: '.' } )
		.pipe( dest( buildDir ) )
}

function pot() {
	return src( [ 'sensei-share-your-grade.php', 'includes/**/*.php' ] )
		.pipe( wpPot( {
			domain: 'sensei-share-your-grade',
			package: 'Sensei Share Your Grade',
		} ) )
		.pipe( dest( 'languages/sensei-share-your-grade.pot' ) );
}

function zipFiles() {
	return src( buildDir + '/**/*', { base: buildDir + '/..' } )
		.pipe( zip( buildDir + '.zip' ) )
		.pipe( dest( '.' ) );
}

exports.clean = clean;
exports.css = css;
exports.docs = docs;
exports.languages = languages;
exports.php = php;
exports.pot = pot;
exports.zipFiles = zipFiles;

if ( process.env.NODE_ENV === 'dev' ) {
	exports.package = series(
		clean,
		parallel(
			css,
			docs,
			series( pot, languages ),
			php,
		),
		zipFiles,
	);
} else {
	exports.package = series(
		clean,
		parallel(
			cssMinify,
			docs,
			series( pot, languages ),
			php,
		),
		zipFiles,
	);
}
