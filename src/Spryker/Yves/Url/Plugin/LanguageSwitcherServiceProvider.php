<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Yves\Url\Plugin;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Spryker\Yves\Kernel\AbstractPlugin;
use Symfony\Component\HttpFoundation\Request;
use Twig_Environment;
use Twig_SimpleFunction;

/**
 * @method \Spryker\Yves\Url\UrlFactory getFactory()
 * @method \Spryker\Client\Url\UrlClientInterface getClient()
 */
class LanguageSwitcherServiceProvider extends AbstractPlugin implements ServiceProviderInterface
{
    /**
     * @param \Silex\Application $app
     *
     * @return void
     */
    public function register(Application $app)
    {
        $app['twig'] = $app->share(
            $app->extend('twig', function (Twig_Environment $twig) {
                $twig->addFunction(
                    $this->createRenderLanguageSwitcherTwigFunction($twig)
                );

                return $twig;
            })
        );
    }

    /**
     * @param \Twig_Environment $twig
     *
     * @return \Twig_SimpleFunction
     */
    protected function createRenderLanguageSwitcherTwigFunction(Twig_Environment $twig)
    {
        return new Twig_SimpleFunction('render_language_switcher', function (Request $request, $templatePath) use ($twig) {
            $currentLanguage = $this->getFactory()->getStore()->getCurrentLanguage();
            $currentUrl = $request->getPathInfo();
            $currentUrlStorage = $this->getClient()->findUrl($currentUrl, $this->getLocale());
            $locales = $this->getFactory()->getStore()->getLocales();

            $localeUrls = [];
            if ($currentUrlStorage !== false && $currentUrlStorage->getLocaleUrls()->count() !== 0) {
                $localeUrls = (array)$currentUrlStorage->getLocaleUrls();
            }

            if (!empty($localeUrls)) {
                return $twig->render(
                    $templatePath,
                    [
                    'languages' => $this->attachLocaleUrlsFromStorageToLanguages($locales, $localeUrls, $request),
                    'currentLanguage' => $currentLanguage,
                    ]
                );
            }

            return $twig->render(
                $templatePath,
                [
                'languages' => $this->attachLocaleUrlsToLanguages($locales, $request),
                'currentLanguage' => $currentLanguage,
                ]
            );
        });
    }

    /**
     * @param array $locales
     * @param \Generated\Shared\Transfer\UrlTransfer[] $localeUrls
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return array
     */
    protected function attachLocaleUrlsFromStorageToLanguages(array $locales, array $localeUrls, Request $request): array
    {
        $languages = [];
        foreach ($locales as $locale) {
            $language = $this->getLanguageFromLocale($locale);
            foreach ($localeUrls as $localeUrl) {
                if (preg_match('/^\/' . $language . '\//', $localeUrl->getUrl())) {
                    $languages[$language] = $localeUrl->getUrl() . '?' . $request->getQueryString();
                    break;
                }
            }
        }

        return $languages;
    }

    /**
     * @param array $locales
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return array
     */
    protected function attachLocaleUrlsToLanguages(array $locales, Request $request): array
    {
        $currentUrl = $request->getRequestUri();
        $languages = [];
        foreach ($locales as $locale) {
            $language = $this->getLanguageFromLocale($locale);
            $languages[$language] = $this->replaceCurrentUrlLanguage($currentUrl, array_keys($locales), $language);
        }

        return $languages;
    }

    /**
     * @param string $currentUrl
     * @param array $languages
     * @param string $replacementLanguage
     *
     * @return string
     */
    protected function replaceCurrentUrlLanguage($currentUrl, array $languages, $replacementLanguage)
    {
        if (preg_match('/\/(' . implode('|', $languages) . ')/', $currentUrl)) {
            return preg_replace('/\/(' . implode('|', $languages) . ')/', '/' . $replacementLanguage, $currentUrl, 1);
        }
        return rtrim('/' . $replacementLanguage . $currentUrl, '/');
    }

    /**
     * @param string $locale
     *
     * @return string
     */
    protected function getLanguageFromLocale($locale): string
    {
        return substr($locale, 0, strpos($locale, '_'));
    }

    /**
     * @param \Silex\Application $app
     *
     * @return void
     */
    public function boot(Application $app)
    {
    }
}