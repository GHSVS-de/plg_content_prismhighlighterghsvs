const ncp = require('ncp').ncp;
const fs = require('fs');
const fse = require('fs-extra');
const path = require('path');
const mkdirp = require('mkdirp');
const util = require("util");
const rimRaf = util.promisify(require("rimraf"));

const {
	author,
	creationDate,
	copyright,
	name,
	version,
	licenseLong,
	minimumPhp,
	maximumPhp,
	minimumJoomla,
	maximumJoomla,
	allowDowngrades,
} = require("./package.json");

const doSomethingAsync1000 = (hallos) =>
{
  return new Promise(resolve => {
    setTimeout(() => {
			// let kack = 1 + 6;
			// console.log(`kack: ${kack}`);
			// "return" nur zur Demo.
			return resolve(hallos)
		}, 1000)
  })
}

const copyCSS = async (workDir, outputPath) =>
{
  return new Promise(resolve => {
		ncp.limit = 16;
		
    ncp(workDir, outputPath, {filter: (source) => {
        if (fs.lstatSync(source).isDirectory())
				{
					return true;
        }
				else
				{
					return source.match(/.*css/) != null;
        }
    }}, // options end here.
		
		function (err) {
        if (err) {
            return console.error(err);
        }
    } //err end here
		
		); // ncp end here

		resolve('I copied plugins CSS in copyCSS().')
  });
}

// https://gist.github.com/liangzan/807712/8fb16263cb39e8472d17aea760b6b1492c465af2
let deleteEmptyDirsRec = async (dirPath, options = {}) => {
  const
    { removeContentOnly = false, drillDownSymlinks = false } = options,
    { promisify } = require('util'),
    path = require('path'),
    fs = require('fs'),
    readdirAsync = promisify(fs.readdir),
    unlinkAsync = promisify(fs.unlink),
    rmdirAsync = promisify(fs.rmdir),
    lstatAsync = promisify(fs.lstat) // fs.lstat can detect symlinks, fs.stat can't
  let
    files

  try {
    files = await readdirAsync(dirPath)
  } catch (e) {
    throw new Error(e)
  }

  if (files.length) {
    for (let fileName of files) {
      let
        filePath = path.join(dirPath, fileName),
        fileStat = await lstatAsync(filePath),
				isDir = fileStat.isDirectory()

      if (isDir) {
        await deleteEmptyDirsRec(filePath)
      } else {
        // await unlinkAsync(filePath)
      }
    }
  }
	
	if (files.length === 0)
	{
		await rmdirAsync(dirPath)
		console.log(`rm: ${dirPath.replace(__dirname, '')}`);
	}
	
	return `I deleted empty folders in deleteEmptyDirsRec()`
}

let deleteFileTypeRec = async (dirPath, fileExtRegEx, options = {}) =>
{
  const
    { removeContentOnly = false, drillDownSymlinks = false } = options,
    { promisify } = require('util'),
    path = require('path'),
    fs = require('fs'),
    readdirAsync = promisify(fs.readdir),
    unlinkAsync = promisify(fs.unlink),
    rmdirAsync = promisify(fs.rmdir),
    lstatAsync = promisify(fs.lstat) // fs.lstat can detect symlinks, fs.stat can't
  let
    files

  try {
    files = await readdirAsync(dirPath)
  } catch (e) {
    throw new Error(e)
  }

  if (files.length)
	{
    for (let fileName of files) {
      let
        filePath = path.join(dirPath, fileName),
        fileStat = await lstatAsync(filePath),
				isDir = fileStat.isDirectory();

      if (isDir) {
        await deleteFileTypeRec(filePath, fileExtRegEx)
      }
			else if (filePath.match(fileExtRegEx))
			{
        await unlinkAsync(filePath);
				console.log(`rm: ${filePath.replace(dirPath, '')}`);
      }
    }
  }
	
	return `Deleted some files in deleteFileTypeRec() that matched '${fileExtRegEx}'.`
}

// Die master-Funktion mit async/await.
/*const doSomething = async (hallo) =>
{*/
(async function exec()
{

	const firstCleanOuts = [
		`./src/media/css/_combiByPlugin`,
		`./src/media/css/prismjs`,
		`./src/media/js/_combiByPlugin`,
		`./src/media/js/prismjs`,
		`./src/media/js/clipboard`,
		`./src/media/prismjs`,
		`./src/vendor`,
		`./package`,
		`./dist`,
		// Conflicts while upload
		'./vendor/bin/',
		'./vendor/matthiasmullie/minify/bin',
		'./src/vendor/bin/',
		'./src/vendor/matthiasmullie/minify/bin'
	];

	for (const file of firstCleanOuts)
	{
		await rimRaf(file).then(
			answer => console.log(`rimrafed: ${file}.`)
		);
	}

	await fse.copy(
		"./node_modules/clipboard/dist",
		"./src/media/js/clipboard"
	).then(
		answer => console.log(`Copied: Clipboard JS.`)
	);

	await fse.copy(
		"./node_modules/prismjs/themes",
		`./src/media/css/prismjs/themes`
	).then(
		answer => console.log('Copied: Prismjs /themes/ CSS.')
	);

	await copyCSS(
		"./node_modules/prismjs/plugins",
		`./src/media/css/prismjs/plugins`
	).then(
		answer => console.log('Copied: Prismjs /plugins/ CSS.')
	);
	
	await doSomethingAsync1000(
		`Did 1000 msec nothing because ncp doesn't wait. F'ing async/sync hell.`
	).then(
		answer => console.log(answer)
	);
	
	await deleteEmptyDirsRec(
		`${__dirname}/src/media/css/prismjs/plugins`
	).then(
		answer => console.log('Deleted: Empty Prismjs /plugins/ CSS folders.')
	);

	await fse.copy(
		"./node_modules/prismjs/plugins",
		`./src/media/js/prismjs/plugins`
	).then(
		answer => console.log('Copied: Prismjs JS /plugins/.')
	);
	await deleteFileTypeRec(
		`${__dirname}/src/media/js/prismjs/plugins`,
		new RegExp('\\.css$')
	).then(
		answer => console.log(answer)
	);

	await fse.copy(
		"./node_modules/prismjs/components",
		`./src/media/js/prismjs/components`
	).then(
		answer => console.log('Copied: Prismjs JS /components/.')
	);

	await fse.copy(
		"./node_modules/prismjs/components.json",
		`./src/media/prismjs/components.json`
	).then(
		answer => console.log(`Copied: Prismjs 'components.json'.`)
	);

	await fse.copy(
		"./node_modules/prismjs/package.json",
		`./src/media/prismjs/package.json`
	).then(
		answer => console.log(`Copied: Prismjs 'package.json' to extract version etc. later.`)
	);

	await fse.copy(
		"./node_modules/prismjs/LICENSE",
		`./src/media/prismjs/LICENSE`
	).then(
		answer => console.log(`Copied: Prismjs 'LICENSE'.`)
	);

	await fse.copy(
		"./vendor",
		`./src/vendor`
	).then(
		answer => console.log(`Copied: /vendor/ PHP.`)
	);

	const copyToDirs = [
		`./src/media/css/_combiByPlugin`,
		`./src/media/js/_combiByPlugin`
	];

	for (const file of copyToDirs)
	{
		await fse.copy("./_combiByPlugin", file
		)
		.then(
			made => console.log(`Created ${copyToDirs}`)
		);
	};

	await fse.copy("./src", "./package");
	await fse.mkdir("./dist");

	let Manifest = "./package/prismhighlighterghsvs.xml";

  let xml = await fse.readFile(Manifest, { encoding: "utf8" });
	xml = xml.replace(/{{name}}/g, name);
	xml = xml.replace(/{{nameUpper}}/g, name.toUpperCase());
	xml = xml.replace(/{{authorName}}/g, author.name);
	xml = xml.replace(/{{creationDate}}/g, creationDate);
	xml = xml.replace(/{{copyright}}/g, copyright);
	xml = xml.replace(/{{licenseLong}}/g, licenseLong);
	xml = xml.replace(/{{authorUrl}}/g, author.url);
  xml = xml.replace(/{{version}}/g, version);
	xml = xml.replace(/{{minimumPhp}}/g, minimumPhp);
	xml = xml.replace(/{{maximumPhp}}/g, maximumPhp);
	xml = xml.replace(/{{minimumJoomla}}/g, minimumJoomla);
	xml = xml.replace(/{{maximumJoomla}}/g, maximumJoomla);
	xml = xml.replace(/{{allowDowngrades}}/g, allowDowngrades);
	
	fse.writeFileSync(Manifest, xml, { encoding: "utf8" });

	const sourceInfos = await JSON.parse(fse.readFileSync(`./package/media/prismjs/package.json`).toString());

	const zip = new (require("adm-zip"))();
  zip.addLocalFolder("package", false);
  zip.writeZip(`dist/${name}-${version}_${sourceInfos.version}.zip`);



})();

