const fse = require('fs-extra');
const path = require('path');
const util = require("util");
const rimRaf = util.promisify(require("rimraf"));
const chalk = require('chalk');
const recursive = require("recursive-readdir");
const replaceXml = require('./build/replaceXml.js');
var CleanCSS = require('clean-css');

const {
	name,
	filename,
	version,
} = require("./package.json");

const manifestFileName = `${filename}.xml`;
const Manifest = `${__dirname}/package/${manifestFileName}`;
const pathMedia = `./media`;

async function cleanOut (cleanOuts) {
	for (const file of cleanOuts)
	{
		await rimRaf(file).then(
			answer => console.log(chalk.redBright(`rimrafed: ${file}.`))
		).catch(error => console.error('Error ' + error));
	}
}

// Nicht async!
function minifyCSS (files, rootPath)
{
	for (const file of files)
	{
		if (
			fse.existsSync(file) && fse.lstatSync(file).isFile() &&
			!file.endsWith('/backend.css') &&
			file.endsWith('.css') &&
			!file.endsWith('.min.css')
			//&& !fse.existsSync(file.replace(`.css`, `.min.css`))
		){
			const content = fse.readFileSync(file, { encoding: 'utf8' });
			var options = { /* options */ };
			const output = new CleanCSS(options).minify(content);

			if (output.errors.length)
			{
				console.log(chalk.redBright(`Errors in minifyCSS()!!!!!!!!!!`));
				console.log(chalk.redBright(file));
				console.log(output.errors);
				process.exit(1);
			}

			if (output.warnings.length)
			{
				console.log(chalk.redBright(`Warnings in minifyCSS()!!!!!!!!!!`));
				console.log(chalk.redBright(file));
				console.log(output.warnings);
			}
			let outputFile = file.replace('.css', '.min.css');
			fse.writeFileSync(outputFile,output.styles, { encoding: 'utf8'});
			outputFile = outputFile.replace(rootPath, '');
			console.log(chalk.greenBright(`Minified: ${outputFile}`));
		}
	}
}

(async function exec()
{
	const versionSub = await JSON.parse(fse.readFileSync(
	`./node_modules/prismjs/package.json`).toString()).version;
	console.log(chalk.yellowBright(`Using Prism version ${versionSub}`));

	let cleanOuts = [
		`./package`,
		`./dist`,
		`${pathMedia}/css/_combiByPlugin`,
		`${pathMedia}/css/prismjs`,
		`${pathMedia}/js/_combiByPlugin`,
		`${pathMedia}/js/prismjs`,
		`${pathMedia}/js/clipboard`,
		`${pathMedia}/json/pluginCssMapJson.json`,
		`${pathMedia}/json/aliasLanguageMap.json`,
		`${pathMedia}/prismjs`,
	];

	await cleanOut(cleanOuts);

	await fse.copy(
		"./node_modules/clipboard/dist",
		`${pathMedia}/js/clipboard`
	).then(
		answer => console.log(chalk.yellowBright(`Copied: Clipboard JS.`))
	);

	await fse.copy(
		"./node_modules/prismjs/themes",
		`${pathMedia}/css/prismjs/themes`
	).then(
		answer => console.log(chalk.yellowBright(`Copied: Prismjs /themes/ CSS.`))
	);

	// ### Fill ./media/js/prismjs/plugins with JS files only! - START
	await fse.copy(
		"./node_modules/prismjs/plugins",
		`${pathMedia}/js/prismjs/plugins`
	).then(
		answer => console.log(chalk.yellowBright(`Copied: Prismjs /plugins/ inclusive CSS to JS folder for further workout.`))
	);

	// #### Remove copied CSS files from ./media/js/prismjs/plugins.
	await recursive(`${pathMedia}/js/prismjs/plugins`).then(
		function(files) {
			let thisRegex = new RegExp('\.css$');

			files.forEach((file) =>
			{
				file = path.join(__dirname, file);

				if (thisRegex.test(file) && fse.existsSync(file)
					&& fse.lstatSync(file).isFile())
				{
					fse.removeSync(file);
				}
			});
		},
		function(error) {
			console.error("something exploded", error);
		}
	);

	console.log(chalk.redBright(`Deleted CSS files inside Prismjs /plugins/js/.`));
	// ### Fill ./media/js/prismjs/plugins with JS files only! - END

	/*
	### Fill ./media/css/prismjs/plugins with CSS files only! - START.
	and then delete empty folders from ./media/css/prismjs/plugins
	*/
	await fse.copy(
		"./node_modules/prismjs/plugins",
		`${pathMedia}/css/prismjs/plugins`
	).then(
		answer => console.log(chalk.yellowBright(`Copied: Prismjs /plugins/ inclusive JS to CSS folder for further workout.`))
	);

	await recursive(`${pathMedia}/css/prismjs/plugins`).then(
		function(files) {
			thisRegex = new RegExp('\.js$');

			files.forEach((file) =>
			{
				file = path.join(__dirname, file);

				if (thisRegex.test(file) && fse.existsSync(file)
					&& fse.lstatSync(file).isFile())
				{
					fse.removeSync(file);
				}
			});
		},
		function(error) {
			console.error("something exploded", error);
		}
	);

	console.log(chalk.redBright(`Deleted JS files inside Prismjs /plugins/css/.`));

	await fse.readdir(`${pathMedia}/css/prismjs/plugins`).then(
		function(folders) {

			folders.forEach((folder) =>
			{
				let folder_ = path.join(__dirname,`${pathMedia}/css/prismjs/plugins`, folder);
				let filesInDir = fse.readdirSync(folder_);

				if (!filesInDir.length)
				{
					fse.removeSync(folder_);
					console.log(chalk.redBright(
						`Deleted now empty folder ${folder} inside Prismjs /plugins/css/.`));
				}
			})
		}
	);
	/*
	### Fill ./media/css/prismjs/plugins with CSS files only! - END.
	*/

	await console.log(chalk.cyanBright(`Be patient! Some copy actions!`));

	await fse.copy(
		"./node_modules/prismjs/components",
		`${pathMedia}/js/prismjs/components`
	).then(
		answer => console.log(chalk.yellowBright(
			'Copied: Prismjs JS /components/.'))
	);

	await fse.copy(
		"./node_modules/prismjs/components.json",
		`${pathMedia}/prismjs/components.json`
	).then(
		answer => console.log(chalk.yellowBright(
			`Copied: Prismjs 'components.json'.`))
	);

	await fse.copy(
		"./node_modules/prismjs/package.json",
		`${pathMedia}/prismjs/package.json`
	).then(
		answer => console.log(chalk.yellowBright(
			`Copied: Prismjs 'package.json'.`))
	);

	await fse.copy(
		"./node_modules/prismjs/LICENSE",
		`${pathMedia}/prismjs/LICENSE`
	).then(
		answer => console.log(chalk.yellowBright(
			`Copied: Prismjs 'LICENSE'.`))
	);

	const copyToDirs = [
		`${pathMedia}/css/_combiByPlugin`,
		`${pathMedia}/js/_combiByPlugin`
	];

	for (const file of copyToDirs)
	{
		await fse.copy("./_combiByPlugin", file
		)
		.then(
			made => console.log(chalk.yellowBright(
				`Created ${file}`))
		);
	};

	await fse.copy(`${pathMedia}`, "./package/media"
	).then(
		answer => console.log(chalk.yellowBright(
			`Copied ${pathMedia} to ./package/media.`))
	);

	// ### Minify CSS - START
	let rootPath = `${__dirname}/package/media/css`;

	await recursive(rootPath, ['!*.+(css)']).then(
		function(files) {
			minifyCSS(files, rootPath);
		},
		function(error) {
			console.error("something exploded", error);
		}
	);
	// ### Minify CSS - ENDE

	await console.log(chalk.cyanBright(
		`Be patient! Composer copy actions!`));
	// Orphans:
	fse.removeSync("./_composer/vendor/bin");
	fse.removeSync("./_composer/vendor/matthiasmullie/minify/bin");
	fse.removeSync("./_composer/vendor/matthiasmullie/minify/.github");
	fse.removeSync("./_composer/vendor/laminas/laminas-zendframework-bridge/.github");
	await fse.copy("./_composer/vendor", `./package/vendor`
	).then(
		answer => console.log(chalk.yellowBright(
			`Copied _composer/vendor to ./package/vendor.`))
	);

	await fse.copy("./src", "./package").then(
		answer => console.log(chalk.yellowBright(
			`Copied ./src/* to ./package.`))
	);

	await fse.mkdir("./dist").then(
		answer => console.log(chalk.greenBright(
			`Created ./dist.`))
	);

	const zipFilename = `${name}-${version}_${versionSub}.zip`;

	await replaceXml.main(Manifest, zipFilename);
	await fse.copy(`${Manifest}`, `./dist/${manifestFileName}`).then(
		answer => console.log(chalk.yellowBright(
			`Copied ${manifestFileName} to ./dist.`))
	);

	let xmlFile = 'update.xml';
	await fse.copy(`./${xmlFile}`, `./dist/${xmlFile}`).then(
		answer => console.log(chalk.yellowBright(
			`Copied ${xmlFile} to ./dist.`))
	);
	await replaceXml.main(`${__dirname}/dist/${xmlFile}`, zipFilename);

	xmlFile = 'changelog.xml';
	await fse.copy(`./${xmlFile}`, `./dist/${xmlFile}`).then(
		answer => console.log(chalk.yellowBright(
			`Copied ${xmlFile} to ./dist.`))
	);
	await replaceXml.main(`${__dirname}/dist/${xmlFile}`, zipFilename);

	// Zip it
	const zip = new (require("adm-zip"))();
	zip.addLocalFolder("package", false);
	await zip.writeZip(`./dist/${zipFilename}`);
	console.log(chalk.cyanBright(chalk.bgRed(
		`./dist/${zipFilename} written.`)));

	cleanOuts = [
		`./package`,
		`${pathMedia}/css/_combiByPlugin`,
		`${pathMedia}/css/prismjs`,
		`${pathMedia}/js/_combiByPlugin`,
		`${pathMedia}/js/prismjs`,
		`${pathMedia}/js/clipboard`,
		`${pathMedia}/prismjs`,
	];
	await cleanOut(cleanOuts).then(
		answer => console.log(chalk.cyanBright(chalk.bgRed(
			`Finished. Good bye!`)))
	);

})();
