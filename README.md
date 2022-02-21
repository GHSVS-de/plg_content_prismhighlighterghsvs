# plg_content_prismhighlighterghsvs
- Joomla content plugin. Syntax highlightning of code snippets.
- Uses JS library  [PrismJS/prism](https://github.com/PrismJS/prism).
- Uses JS library  [zenorocha/clipboard.js](https://github.com/zenorocha/clipboard.js).
- Uses PHP library [laminas/laminas-dom](https://github.com/laminas/laminas-dom).
- Uses PHP library [matthiasmullie/minify](https://github.com/matthiasmullie/minify).

# Be aware
- **Work in (long) progress!!**
- But works on my public, private website for a long time now.

# Prism docs/infos
- [Supported languages and aliases](https://prismjs.com/index.html#supported-languages)
- [Per language examples](https://prismjs.com/examples.html#per-language-examples)
- [Plugins](https://prismjs.com/index.html#plugins)

# My personal build procedure (WSL 1, Debian, Win 10)
- Prepare/adapt `./package.json`.
- `cd /mnt/z/git-kram/plg_content_prismhighlighterghsvs`

## node/npm updates/installation
- if not done yet
- - `npm install` (if needed)
### Updates
- `npm run g-npm-update-check` or (faster) `npm outdated`
- `npm run g-npm-update` (if needed) or (faster) `npm update --save-dev`


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
- The second version after underscore in zip filename is the used prismjs version.
- All packed files for this ZIP can be seen in `./package`. **But only if you disable deletion of this folder at the end of `build.js`**.s

#### For Joomla update server
- Create new release with new tag.
- Get download link for new `dist/plg_blahaba_blubber...zip` **from newly created tag branch** and add to release description.
- Extracts(!) of the update and changelog XML for update and changelog servers are in `./dist` as well. Check for necessary additions! Then copy/paste.
