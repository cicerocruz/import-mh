<?php
namespace Cruz\WebtreesModules\importmh;
/*
 * webtrees - Cruz_MyHeritage tab based on simpl_MyHeritage
 *
 * Copyright (C) 2013 Vytautas Krivickas and Cruz.com. All rights reserved. 
 *
 * Copyright (C) 2013 Nigel Osborne and kiwtrees.net. All rights reserved.
 *
 * webtrees: Web based Family History software
 * Copyright (C) 2013 webtrees development team.
 *
 * Derived from PhpGedView
 * Copyright (C) 2002 to 2010  PGV Development Team.  All rights reserved.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\GedcomCode\GedcomCodePedi;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Module\ModuleTabTrait;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Fisharebest\Localization\Translation;
use Psr\Http\Message\ResponseInterface;

/**
 * Cruz_MyHeritage module
 */
class ImportMHTabModule extends AbstractModule implements ModuleTabInterface, ModuleCustomInterface
{
    use ModuleCustomTrait;
    use ModuleTabTrait;

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title() : string
    {
        return /* I18N: Name of a module/tab on the individual page. */ I18N::translate('MyHeritage');
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description() : string
    {
        return /* I18N: Description of the "Facts and events" module */ I18N::translate('A tab showing MyHeritage of an individual.');
    }

    /**
     * The person or organisation who created this module.
     *
     * @return string
     */
    public function customModuleAuthorName() : string
    {
        return 'Cicero Cruz';
    }

    /**
     * The version of this module.
     *
     * @return string
     */
    public function customModuleVersion() : string
    {
        return '0.0.0';
    }

    /**
     * A URL that will provide the latest version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl() : string
    {
        return 'https://raw.githubusercontent.com/cicerocruz/importmh/master/latest.txt';
    }

    /**
     * Where to get support for this module.  Perhaps a github respository?
     *
     * @return string
     */
    public function customModuleSupportUrl() : string
    {
        return 'https://github.com/cicerocruz/importmh';
    }

    /**
     * The default position for this tab.  It can be changed in the control panel.
     *
     * @return int
     */
    public function defaultTabOrder() : int
    {
        return 11;
    }

    /**
     * Is this tab empty? If so, we don't always need to display it.
     *
     * @param Individual $individual
     *
     * @return bool
     */
    public function hasTabContent(Individual $individual) : bool
    {
        return true;
    }

    /**
     * A greyed out tab has no actual content, but may perhaps have
     * options to create content.
     *
     * @param Individual $individual
     *
     * @return bool
     */
    public function isGrayedOut(Individual $individual) : bool
    {
        return false;
    }

    private function getMyHeritage(Individual $individual) : object
    {
        $MyHeritageObj = (object)[];
        $MyHeritageObj->self = $individual;
        $MyHeritageObj->fathersCousinCount = 0;
        $MyHeritageObj->mothersCousinCount = 0;
        $MyHeritageObj->allCousinCount = 0;
        $MyHeritageObj->fatherMyHeritage = [];
        $MyHeritageObj->motherMyHeritage = [];
        if ($individual->childFamilies()->first()) {
            $MyHeritageObj->father = $individual->childFamilies()->first()->husband();
            if (($MyHeritageObj->father) && ($MyHeritageObj->father->childFamilies()->first())) {
                foreach ($MyHeritageObj->father->childFamilies()->first()->children() as $sibling) {
                    if ($sibling !== $MyHeritageObj->father) {
                        foreach ($sibling->spouseFamilies() as $fam) {
                            foreach ($fam->children() as $child) {
                                $MyHeritageObj->fatherMyHeritage[] = $child;
                                $MyHeritageObj->fathersCousinCount++;
                            }
                        }
                    }
                }
            }

            $MyHeritageObj->mother = $individual->childFamilies()->first()->wife();
            if (($MyHeritageObj->mother) && ($MyHeritageObj->mother->childFamilies()->first())) {
                foreach ($MyHeritageObj->mother->childFamilies()->first()->children() as $sibling) {
                    if ($sibling !== $MyHeritageObj->mother) {
                        foreach ($sibling->spouseFamilies() as $fam) {
                            foreach ($fam->children() as $child) {
                                $MyHeritageObj->motherMyHeritage[] = $child;
                                $MyHeritageObj->mothersCousinCount++;
                            }
                        }
                    }
                }
            }

            $MyHeritageObj->allCousinCount = sizeof(array_unique(array_merge($MyHeritageObj->fatherMyHeritage, $MyHeritageObj->motherMyHeritage)));
        }

        return $MyHeritageObj;
    }

    /**
     * Where does this module store its resources
     *
     * @return string
     */
    public function resourcesFolder() : string
    {
        return __DIR__ . '/resources/';
    }

    /**
     * A label for a parental family group
     *
     * @param Family $family
     *
     * @return string
     */
    public function getChildLabel(Individual $individual) : string
    {
        if (preg_match(
            '/\n1 FAMC @' . $individual->childFamilies()->first()->xref() . '@(?:\n[2-9].*)*\n2 PEDI (.+)/',
            $individual->gedcom(),
            $match
        )) {
            // A specified pedigree
            return GedcomCodePedi::getValue($match[1], $individual->getInstance($individual->xref(), $individual->tree()));
        }

        // Default (birth) pedigree
        return GedcomCodePedi::getValue('', $individual->getInstance($individual->xref(), $individual->tree()));
    }

    /**
     * @return ResponseInterface
     */
    function getCssAction() : ResponseInterface
    {
        return response(
            file_get_contents($this->resourcesFolder() . 'css/Cruz_MyHeritage.css'),
            200,
            ['Content-type' => 'text/css']
        );
    }

    /** {@inheritdoc} */
    public function getTabContent(Individual $individual) : string
    {
        return view(
            $this->name() . '::tab',
            [
                'myheritage_obj'   => $this->getMyHeritage($individual),
                'myheritage_css'   => route('module', ['module' => $this->name(), 'action' => 'Css']),
                'module_obj'    => $this,
            ]
        );
    }

    /** {@inheritdoc} */
    public function canLoadAjax() : bool
    {
        return false;
    }

    /**
     *  Constructor.
     */
    public function __construct() {
        // IMPORTANT - the constructor is called on *all* modules, even ones that are disabled.
        // It is also called before the webtrees framework is initialised, and so other components
        // will not yet exist.
    }

    /**
     *  Boostrap.
     *
     * @param UserInterface $user A user (or visitor) object.
     * @param Tree|null     $tree Note that $tree can be null (if all trees are private).
     */
    public function boot() : void
    {
        // Here is also a good place to register any views (templates) used by the module.
        // This command allows the module to use: view($this->name() . '::', 'fish')
        // to access the file ./resources/views/fish.phtml
        View::registerNamespace($this->name(), __DIR__ . '/resources/views/');
    }

    /**
     * Additional/updated translations.
     *
     * @param string $language
     *
     * @return string[]
     */
    public function customTranslations(string $language) : array
    {
        // Here we are using an array for translations.
        // If you had .MO files, you could use them with:
        // return (new Translation('path/to/file.mo'))->asArray();
        switch ($language) {
            case 'da':
                return $this->danishTranslations();

            case 'fi':
                return $this->finnishTranslations();

            case 'fr':
            case 'fr-CA':
                return $this->frenchTranslations();

            case 'he':
                return $this->hebrewTranslations();

            case 'lt':
                return $this->lithuanianTranslations();

            case 'nb':
                return $this->norwegianBokm??lTranslations();

            case 'nl':
                return $this->dutchTranslations();

            case 'nn':
                return $this->norwegianNynorskTranslations();

            case 'sv':
                return $this->swedishTranslations();

            case 'cs':
                return $this->czechTranslations();

            case 'de':
                return $this->germanTranslations();

            default:
                return [];
        }
    }

    /**
     * @return array
     */
    protected function lithuanianTranslations() : array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'MyHeritage' => 'Pusbroliai / Pusseser??s',
            'A tab showing MyHeritage of an individual.' => 'Lapas rodantis asmens pusbrolius ir pusseseres.',
            'No family available' => '??eima nerasta',
            'Father\'s family (%s)' => 'T??vo ??eima (%s)',
            'Mother\'s family (%s)' => 'Motinos ??eima (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first MyHeritage recorded' => '%2$s turi %1$d ??ra??yta pirmos eil??s pusbrol??/pusseser??'
                . I18N::PLURAL . '%2$s turi %1$d ??ra??ytus pirmos eil??s pusbrolius/pusseseres'
                . I18N::PLURAL . '%2$s turi %1$d ??ra??yt?? pirmos eil??s pusbroli??/pusseseri??',
        ];
    }

    /**
     * @return array
     */
    protected function germanTranslations() : array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'MyHeritage' => 'MyHeritage und Cousinen',
            'A tab showing MyHeritage of an individual.' => 'Ein Reiter, der MyHeritage und Cousinen der Person anzeigt.',
            'No family available' => 'Es gibt keine Familie',
            'Father\'s family (%s)' => 'V??terlicherseits (%s)',
            'Mother\'s family (%s)' => 'M??tterlicherseits (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first MyHeritage recorded' => '%2$s hat einen Cousin oder eine Cousine ersten Grades'
                . I18N::PLURAL . '%2$s hat %1$d MyHeritage oder Cousinen ersten Grades',
        ];
    }

    /**
     * @return array
     */
    protected function danishTranslations() : array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'MyHeritage' => 'F??tre og kusiner',
            'A tab showing MyHeritage of an individual.' => 'En fane der viser en persons f??tre og kusiner.',
            'No family available' => 'Ingen familie tilg??ngelig',
            'Father\'s family (%s)' => 'Fars familie (%s)',
            'Mother\'s family (%s)' => 'Mors familie (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first MyHeritage recorded' => '%2$s har %1$d registreret f??ter eller kusin'
                . I18N::PLURAL . '%2$s har %1$d registrerede f??ter eller kusiner',
        ];
    }

    /**
     * @return array
     */
    protected function frenchTranslations() : array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'MyHeritage' => 'MyHeritage',
            'A tab showing MyHeritage of an individual.' => 'Onglet montrant les MyHeritage d\'un individu.',
            'No family available' => 'Pas de famille disponible',
            'Father\'s family (%s)' => 'Famille paternelle (%s)',
            'Mother\'s family (%s)' => 'Famille maternelle (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first MyHeritage recorded' => '%2$s a %1$d cousin germain connu'
                . I18N::PLURAL . '%2$s a %1$d MyHeritage germains connus',
        ];
    }

    /**
     * @return array
     */
    protected function finnishTranslations() : array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'MyHeritage' => 'Serkut',
            'A tab showing MyHeritage of an individual.' => 'V??lilehti joka n??ytt???? henkil??n serkut.',
            'No family available' => 'Perhe puuttuu',
            'Father\'s family (%s)' => 'Is??n perhe (%s)',
            'Mother\'s family (%s)' => '??idin perhe (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first MyHeritage recorded' => '%2$s:ll?? on %1$d serkku sivustolla'
                . I18N::PLURAL . '%2$s:lla on %1$d serkkua sivustolla',
        ];
    }

    /**
     * @return array
     */
    protected function hebrewTranslations() : array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'MyHeritage' => '?????? ??????????',
            'A tab showing MyHeritage of an individual.' => '???????? ?????????? ?????? ?????? ???? ??????.',
            'No family available' => '?????????? ????????',
            'Father\'s family (%s)' => '?????????? ?????? (%s)',
            'Mother\'s family (%s)' => '?????????? ?????? (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first MyHeritage recorded' => '??%2$s ???? ???? ?????? ?????? ?????????? ????????????'
                . I18N::PLURAL . '??%2$s ???? %1$d ?????? ?????????? ?????????? ????????????',
        ];
    }

    /**
     * @return array
     */
    protected function norwegianBokm??lTranslations() : array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'MyHeritage' => 'S??skenbarn',
            'A tab showing MyHeritage of an individual.' => 'Fane som viser en persons s??skenbarn.',
            'No family available' => 'Ingen familie tilgjengelig',
            'Father\'s family (%s)' => 'Fars familie (%s)',
            'Mother\'s family (%s)' => 'Mors familie (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first MyHeritage recorded' => '%2$s har %1$d registrert s??skenbarn'
                . I18N::PLURAL . '%2$s har %1$d registrerte s??skenbarn',
        ];
    }

    /**
     * @return array
     */
    protected function norwegianNynorskTranslations() : array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'MyHeritage' => 'Syskenbarn',
            'A tab showing MyHeritage of an individual.' => 'Fane som syner ein person sine syskenbarn.',
            'No family available' => 'Ingen familie tilgjengeleg',
            'Father\'s family (%s)' => 'Fars familie (%s)',
            'Mother\'s family (%s)' => 'Mors familie (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first MyHeritage recorded' => '%2$s har %1$d registrert syskenbarn'
                . I18N::PLURAL . '%2$s har %1$d registrerte syskenbarn',
        ];
    }

    /**
     * @return array
     */
    protected function dutchTranslations() : array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'MyHeritage' => 'Neven en Nichten',
            'A tab showing MyHeritage of an individual.' => 'Tab laat neven en nichten van deze persoon zien.',
            'No family available' => 'Geen familie gevonden',
            'Father\'s family (%s)' => 'Vader\'s familie (%s)',
            'Mother\'s family (%s)' => 'Moeder\'s familie (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first MyHeritage recorded' => '%2$s heeft %1$d neef of nicht in de eerste lijn'
                . I18N::PLURAL . '%2$s heeft %1$d neven en nichten in de eerste lijn',
        ];
    }

    /**
     * @return array
     */
    protected function swedishTranslations() : array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'MyHeritage' => 'Kusiner',
            'A tab showing MyHeritage of an individual.' => 'En flik som visar en persons kusiner.',
            'No family available' => 'Familj saknas',
            'Father\'s family (%s)' => 'Faderns familj (%s)',
            'Mother\'s family (%s)' => 'Moderns familj (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first MyHeritage recorded' => '%2$s har %1$d registrerad kusin'
                . I18N::PLURAL . '%2$s har %1$d registrerade kusiner',
        ];
    }

    /**
     * @return array
     */
    protected function czechTranslations() : array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'MyHeritage' => 'Bratranci',
            'A tab showing MyHeritage of an individual.' => 'Panel zobrazuj??c?? bratrance osoby.',
            'No family available' => 'Rodina chyb??',
            'Father\'s family (%s)' => 'Otcova rodina (%s)',
            'Mother\'s family (%s)' => 'Mat??ina rodina (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first MyHeritage recorded' => '%2$s m?? %1$d bratrance'
                . I18N::PLURAL . '%2$s m?? %1$d bratrance'
                . I18N::PLURAL . '%2$s m?? %1$d bratranc??',
        ];
    }
};

return new ImportMHTabModule;
