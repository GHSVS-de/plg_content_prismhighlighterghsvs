const fse = require('fs-extra');
const path = require('path');
const util = require("util");
const rimRaf = util.promisify(require("rimraf"));
const chalk = require('chalk');
const recursive = require("recursive-readdir");

const {
	author,
	update,
	copyright,
	creationDate,
	description,
	name,
	filename,
	version,
	versionCompare,
	licenseLong,
	minimumPhp,
	maximumPhp,
	minimumJoomla,
	maximumJoomla,
	allowDowngrades,
} = require("./package.json");

const manifestFileName = `${filename}.xml`;
const Manifest = `./package/${manifestFileName}`;
const pathMedia = `./media`;

async function cleanOut (cleanOuts) {
	for (const file of cleanOuts)
	{
		await rimRaf(file).then(
			answer => console.log(chalk.redBright(`rimrafed: ${file}.`))
		).catch(error => console.error('Error ' + error));
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

	let xml = await fse.readFile(Manifest, { encoding: "utf8" });
	xml = xml.replace(/{{name}}/g, name);
	xml = xml.replace(/{{filename}}/g, filename);
	xml = xml.replace(/{{nameUpper}}/g, name.toUpperCase());
	xml = xml.replace(/{{authorName}}/g, author.name);
	xml = xml.replace(/{{creationDate}}/g, creationDate);
	xml = xml.replace(/{{copyright}}/g, copyright);
	xml = xml.replace(/{{licenseLong}}/g, licenseLong);
	xml = xml.replace(/{{authorUrl}}/g, author.url);
	xml = xml.replace(/{{version}}/g, version);
	xml = xml.replace(/{{versionCompare}}/g, versionCompare);
	xml = xml.replace(/{{minimumPhp}}/g, minimumPhp);
	xml = xml.replace(/{{maximumPhp}}/g, maximumPhp);
	xml = xml.replace(/{{minimumJoomla}}/g, minimumJoomla);
	xml = xml.replace(/{{maximumJoomla}}/g, maximumJoomla);
	xml = xml.replace(/{{allowDowngrades}}/g, allowDowngrades);
	xml = xml.replace(/{{zipFilename}}/g, zipFilename);

	await fse.writeFile(Manifest, xml, { encoding: "utf8" }
	).then(
		answer => console.log(chalk.yellowBright(
			`Replaced entries in ${Manifest}.`))
	);

	await fse.copy(`${Manifest}`, `./dist/${manifestFileName}`).then(
		answer => console.log(chalk.yellowBright(
			`Copied ${manifestFileName} to ./dist.`))
	);

	await fse.copy(`./update.xml`, `./dist/update.xml`).then(
		answer => console.log(chalk.yellowBright(
			`Copied update.xml to ./dist.`))
	);

	xml = await fse.readFile(`./dist/update.xml`, { encoding: "utf8" });
	xml = xml.replace(/{{nameUpper}}/g, name.toUpperCase());
	xml = xml.replace(/{{description}}/g, description);
	xml = xml.replace(/{{element}}/g, filename);
	xml = xml.replace(/{{type}}/g, update.type);
	xml = xml.replace(/{{folder}}/g, update.folder);
	xml = xml.replace(/{{client}}/g, update.client);
	xml = xml.replace(/{{version}}/g, version);
	xml = xml.replace(/{{name}}/g, name);
	xml = xml.replace(/{{zipFilename}}/g, zipFilename);
	xml = xml.replace(/{{tag}}/g, update.tag);
	xml = xml.replace(/{{maintainer}}/g, author.name);
	xml = xml.replace(/{{maintainerurl}}/g, author.url);
	xml = xml.replace(/{{targetplatform}}/g, update.targetplatform);
	xml = xml.replace(/{{php_minimum}}/g, minimumPhp);

	await fse.writeFile(`./dist/update.xml`, xml, { encoding: "utf8" }
	).then(
		answer => console.log(chalk.yellowBright(
			`Replaced entries in ./dist/update.xml.`))
	);

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
