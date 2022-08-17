#!/usr/bin/env node
const path = require('path');

/* Configure START */
const pathBuildKram = path.resolve("../buildKramGhsvs");
const updateXml = `${pathBuildKram}/build/update.xml`;
const changelogXml = `${pathBuildKram}/build/changelog.xml`;
const releaseTxt = `${pathBuildKram}/build/release.txt`;
/* Configure END */

const replaceXml = require(`${pathBuildKram}/build/replaceXml.js`);
const helper = require(`${pathBuildKram}/build/helper.js`);

const fse = require(`${pathBuildKram}/node_modules/fs-extra`);
const pc = require(`${pathBuildKram}/node_modules/picocolors`);
const recursive = require("recursive-readdir");

const {
	name,
	filename,
	version,
} = require("./package.json");

const manifestFileName = `${filename}.xml`;
const Manifest = `${__dirname}/package/${manifestFileName}`;
const source = `${__dirname}/node_modules/prismjs`;
const target = `./media`;
let versionSub = '';

let replaceXmlOptions = {
	"xmlFile": "",
	"zipFilename": "",
	"checksum": "",
	"dirname": __dirname,
	"jsonString": "",
	"versionSub": ""
};
let zipOptions = {};
let from = "";
let to = "";

(async function exec()
{
	let cleanOuts = [
		`./package`,
		`./dist`,
		`${target}/css/_combiByPlugin`,
		`${target}/css/prismjs`,
		`${target}/js/_combiByPlugin`,
		`${target}/js/prismjs`,
		`${target}/js/clipboard`,
		`${target}/json/pluginCssMapJson.json`,
		`${target}/json/aliasLanguageMap.json`,
		`${target}/prismjs`,
	];

	await helper.cleanOut(cleanOuts);

	versionSub = await helper.findVersionSubSimple (
		path.join(source, `package.json`),
		'prismjs');
	console.log(pc.magenta(pc.bold(`versionSub identified as: "${versionSub}"`)));

	replaceXmlOptions.versionSub = versionSub;

	from = `./node_modules/clipboard/dist`;
	to = `${target}/js/clipboard`;
	await helper.copy(from, to)

	from = path.join(source, 'themes');
	to = `${target}/css/prismjs/themes`;
	await fse.copy(from, to
	).then(
		answer => console.log(
			pc.yellow(pc.bold(`Copied "${from}" to "${to}". Is pure CSS.`))
		)
	);

	// ### Fill ./media/(!)js(!)/prismjs/plugins with JS files only! - START
	from = path.join(source, 'plugins');
	to = `${target}/js/prismjs/plugins`;
	await fse.copy(from, to
	).then(
		answer => console.log(
			pc.yellow(pc.bold(`Copied "${from}" to "${to}" inclusive CSS to JS folder for further workout.`))
		)
	);

	// #### Remove copied CSS files from ./media/(!)js(!)/prismjs/plugins.
	await recursive(to).then(
		function(files)
		{
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
			console.error("something exploded in recursive().", error);
		}
	);

	console.log(pc.red(pc.bold(
		`Deleted CSS files inside Prismjs /plugins/js/.`)));
	// ### Fill ./media/js/prismjs/plugins with JS files only! - END

	/*
	### Fill ./media/css/prismjs/plugins with CSS files only! - START.
	and then delete empty folders from ./media/css/prismjs/plugins
	*/
	to = `${target}/css/prismjs/plugins`;
	await fse.copy(from, to
	).then(
		answer => console.log(
			pc.yellow(pc.bold(`Copied "${from}" to "${to}" inclusive JS to CSS folder for further workout.`))
		)
	);

	await recursive(to).then(
		function(files)
		{
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
			console.error("something exploded in recursive().", error);
		}
	);

	console.log(pc.red(pc.bold(
		`Deleted JS files inside Prismjs /plugins/css/.`)));

	await fse.readdir(to).then(
		function(folders)
		{
			folders.forEach((folder) =>
			{
				let folder_ = path.join(__dirname, to, folder);
				let filesInDir = fse.readdirSync(folder_);

				if (!filesInDir.length)
				{
					fse.removeSync(folder_);
					console.log(
						pc.red(pc.bold(
							`Deleted empty folder ${folder} inside Prismjs /plugins/css/.`))
						);
				}
			})
		}
	);
	/*
	### Fill ./media/css/prismjs/plugins with CSS files only! - END.
	*/

	from = "./node_modules/prismjs/components";
	to = `${target}/js/prismjs/components`;
	await fse.copy(from, to
	).then(
		answer => console.log(
			pc.yellow(pc.bold(`Copied "${from}" to "${to}". Is pure JS.`))
		)
	);

	from = "./node_modules/prismjs/components.json";
	to =`${target}/prismjs/components.json`;
	await helper.copy(from, to)

	from = "./node_modules/prismjs/package.json";
	to =`${target}/prismjs/package.json`;
	await helper.copy(from, to)

	from = "./node_modules/prismjs/LICENSE";
	to =`${target}/prismjs/LICENSE`;
	await helper.copy(from, to)

	const copyToDirs = [
		`${target}/css/_combiByPlugin`,
		`${target}/js/_combiByPlugin`
	];

	for (const file of copyToDirs)
	{
		await fse.copy("./_combiByPlugin", file
		).then(
			answer => console.log(
				pc.yellow(pc.bold(`Created "${file}".`))
			)
		);
	}

	from = target;
	to = `./package/media`;
	await helper.copy(from, to)

	await helper.gzip([to]);

	await console.log(pc.cyan(pc.bold(`Be patient! Composer actions!`)));

	// Orphans:
	fse.removeSync("./_composer/vendor/bin");
	fse.removeSync("./_composer/vendor/matthiasmullie/minify/bin");
	fse.removeSync("./_composer/vendor/matthiasmullie/minify/.github");
	fse.removeSync("./_composer/vendor/laminas/laminas-zendframework-bridge/.github");

	from = `./_composer/vendor`;
	to = `./package/vendor`;
	await helper.copy(from, to)

	from = `./src`;
	to = `./package`;
	await helper.copy(from, to)

	await helper.mkdir('./dist');

	const zipFilename = `${name}-${version}_${versionSub}.zip`;

	replaceXmlOptions = {
		"xmlFile": Manifest,
		"zipFilename": zipFilename,
		"checksum": "",
		"dirname": __dirname
	};

	await replaceXml.main(replaceXmlOptions);
	await fse.copy(`${Manifest}`, `./dist/${manifestFileName}`).then(
		answer => console.log(pc.yellow(pc.bold(
			`Copied "${manifestFileName}" to "./dist".`)))
	);

	// Create zip file and detect checksum then.
	const zipFilePath = path.resolve(`./dist/${zipFilename}`);

	zipOptions = {
		"source": path.resolve("package"),
		"target": zipFilePath
	};
	await helper.zip(zipOptions)

	replaceXmlOptions.checksum = await helper._getChecksum(zipFilePath);

	// Bei diesen werden zuerst Vorlagen nach dist/ kopiert und dort erst "replaced".
	for (const file of [updateXml, changelogXml, releaseTxt])
	{
		from = file;
		to = `./dist/${path.win32.basename(file)}`;
		await helper.copy(from, to)

		replaceXmlOptions.xmlFile = path.resolve(to);
		await replaceXml.main(replaceXmlOptions);
	}

	cleanOuts = [
		`./package`,
		`${target}/css/_combiByPlugin`,
		`${target}/css/prismjs`,
		`${target}/js/_combiByPlugin`,
		`${target}/js/prismjs`,
		`${target}/js/clipboard`,
		`${target}/prismjs`,
	];
	await helper.cleanOut(cleanOuts).then(
		answer => console.log(pc.cyan(pc.bold(pc.bgRed(
			`Finished. Good bye!`))))
	);
})();
