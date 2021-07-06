# plg_content_prismhighlighterghsvs
- Joomla content plugin. Syntax highlightning of code snippets.
- Uses JS library  [PrismJS/prism](https://github.com/PrismJS/prism).
- Uses JS library  [zenorocha/clipboard.js](https://github.com/zenorocha/clipboard.js).
- Uses PHP library [laminas/laminas-dom](https://github.com/laminas/laminas-dom).
- Uses PHP library [matthiasmullie/minify](https://github.com/matthiasmullie/minify).

# Be aware
- **Work in (long) progress!!**
- But works on my public, private website for a long time now.

# My personal build procedure (WSL 1, Debian, Win 10)
- Prepare/adapt `./package.json`.
- `cd /mnt/z/git-kram/plg_content_prismhighlighterghsvs`

## node/npm updates/installation
- `npm run g-npm-update-check` or (faster) `ncu`
- `npm run g-ncu-override-json` (if needed) or (faster) `ncu -u`
- `npm install` (if needed)

## composer
- The composer.json is located in folder `./_composer`
- Check for PHP libraries updates.

```
cd _composer/

composer outdated

OR

composer show -l
```
- both commands accept the parameter `--direct` to show only direct dependencies in the listing

- If somethig to bump/update:

```
composer update

OR

composer install
```

## Build installable ZIP package
- `cd ../` if you're still in `_composer/`.
- `node build.js`
- New, installable ZIP is in `./dist` afterwards.
- FYI: Packed files for this ZIP can be seen in `./package`. **But only if you disable deletion of this folder at the end of `build.js`**.s




- Run `ncu` and `ncu -u` to bump versions if updates for PrismJS/prism or zenorocha/clipboard.js.
- Run npm install
- Adapt package.json (infos like version of Joomla plugin).
- run node build.js.

#####
- New ZIP is in `/dist/`
- FYI: Packed files for this ZIP can be seen in `/package/`.

- The second version after underscore in zip name is used prismjs version.

#### For Joomla update server
- Create new release with new tag.
- Get download link for new `dist/plg_blahaba_blubber...zip` **from newly created tag branch** and add to release description and add to update server XML.
