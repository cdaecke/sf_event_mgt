<?php
namespace DERHANSEN\SfEventMgt\Controller;

/*
 * This file is part of the Extension "sf_event_mgt" for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use DERHANSEN\SfEventMgt\Domain\Model\Dto\CustomNotification;
use DERHANSEN\SfEventMgt\Domain\Model\Dto\EventDemand;
use DERHANSEN\SfEventMgt\Domain\Model\Dto\SearchDemand;
use DERHANSEN\SfEventMgt\Domain\Model\Event;
use DERHANSEN\SfEventMgt\Service;
use DERHANSEN\SfEventMgt\Utility\MiscUtility;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter;
use TYPO3\CMS\Lang\LanguageService;

/**
 * AdministrationController
 *
 * Several parts are heavily inspired by ext:news from Georg Ringer
 *
 * @author Torben Hansen <derhansen@gmail.com>
 */
class AdministrationController extends AbstractController
{
    const LANG_FILE = 'LLL:EXT:sf_event_mgt/Resources/Private/Language/locallang_be.xlf:';

    /**
     * Backend Template Container
     *
     * @var string
     */
    protected $defaultViewObjectName = \TYPO3\CMS\Backend\View\BackendTemplateView::class;

    /**
     * CustomNotificationLogRepository
     *
     * @var \DERHANSEN\SfEventMgt\Domain\Repository\CustomNotificationLogRepository
     */
    protected $customNotificationLogRepository = null;

    /**
     * ExportService
     *
     * @var \DERHANSEN\SfEventMgt\Service\ExportService
     */
    protected $exportService = null;

    /**
     * SettingsService
     *
     * @var \DERHANSEN\SfEventMgt\Service\SettingsService
     */
    protected $settingsService = null;

    /**
     * Backend User Session Service
     *
     * @var \DERHANSEN\SfEventMgt\Service\BeUserSessionService
     */
    protected $beUserSessionService = null;

    /**
     * @var \DERHANSEN\SfEventMgt\Service\MaintenanceService
     */
    protected $maintenanceService = null;

    /**
     * The current page uid
     *
     * @var int
     */
    protected $pid = 0;

    /**
     * BackendTemplateContainer
     *
     * @var BackendTemplateView
     */
    protected $view;

    /**
     * @var IconFactory
     */
    protected $iconFactory = null;

    /**
     * DI for $customNotificationLogRepository
     *
     * @param \DERHANSEN\SfEventMgt\Domain\Repository\CustomNotificationLogRepository $customNotificationLogRepository
     */
    public function injectCustomNotificationLogRepository(
        \DERHANSEN\SfEventMgt\Domain\Repository\CustomNotificationLogRepository $customNotificationLogRepository
    ) {
        $this->customNotificationLogRepository = $customNotificationLogRepository;
    }

    /**
     * DI for $exportService
     *
     * @param Service\ExportService $exportService
     */
    public function injectExportService(\DERHANSEN\SfEventMgt\Service\ExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * DI for $settingsService
     *
     * @param Service\SettingsService $settingsService
     */
    public function injectSettingsService(\DERHANSEN\SfEventMgt\Service\SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * DI for $beUserSessionService
     *
     * @param Service\BeUserSessionService $beUserSessionService
     */
    public function injectBeUserSessionService(\DERHANSEN\SfEventMgt\Service\BeUserSessionService $beUserSessionService)
    {
        $this->beUserSessionService = $beUserSessionService;
    }

    /**
     * DI for $iconFactory
     *
     * @param IconFactory $iconFactory
     */
    public function injectIconFactory(IconFactory $iconFactory)
    {
        $this->iconFactory = $iconFactory;
    }

    /**
     * @param Service\MaintenanceService $maintenanceService
     */
    public function injectMaintenanceService(\DERHANSEN\SfEventMgt\Service\MaintenanceService $maintenanceService)
    {
        $this->maintenanceService = $maintenanceService;
    }

    /**
     * Set up the doc header properly here
     *
     * @param ViewInterface $view
     *
     * @return void
     */
    protected function initializeView(ViewInterface $view)
    {
        /** @var BackendTemplateView $view */
        parent::initializeView($view);
        if ($this->actionMethodName === 'listAction'
            || $this->actionMethodName === 'indexNotifyAction'
            || $this->actionMethodName === 'settingsErrorAction'
        ) {
            $this->registerDocHeaderButtons();

            $pageRenderer = $this->view->getModuleTemplate()->getPageRenderer();
            $pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/DateTimePicker');

            $dateFormat = $GLOBALS['TYPO3_CONF_VARS']['SYS']['USdateFormat'] ?
                ['MM-DD-YYYY', 'HH:mm MM-DD-YYYY'] :
                ['DD-MM-YYYY', 'HH:mm DD-MM-YYYY'];
            $pageRenderer->addInlineSetting('DateTimePicker', 'DateFormat', $dateFormat);

            $this->view->getModuleTemplate()->setFlashMessageQueue($this->controllerContext->getFlashMessageQueue());
            if ($view instanceof BackendTemplateView) {
                $view->getModuleTemplate()->getPageRenderer()->addCssFile(
                    'EXT:sf_event_mgt/Resources/Public/Css/administration.css'
                );
            }
        }
    }

    /**
     * Register docHeaderButtons
     *
     * @return void
     */
    protected function registerDocHeaderButtons()
    {
        $buttonBar = $this->view->getModuleTemplate()->getDocHeaderComponent()->getButtonBar();

        $uriBuilder = $this->objectManager->get(UriBuilder::class);
        $uriBuilder->setRequest($this->request);

        if ($this->request->getControllerActionName() === 'list') {
            $buttons = [
                [
                    'label' => 'administration.newEvent',
                    'action' => 'newEvent',
                    'icon' => 'ext-sfeventmgt-event',
                    'group' => 1
                ],
                [
                    'label' => 'administration.newLocation',
                    'action' => 'newLocation',
                    'icon' => 'ext-sfeventmgt-location',
                    'group' => 1
                ],
                [
                    'label' => 'administration.newOrganisator',
                    'action' => 'newOrganisator',
                    'icon' => 'ext-sfeventmgt-organisator',
                    'group' => 1
                ],
                [
                    'label' => 'administration.newSpeaker',
                    'action' => 'newSpeaker',
                    'icon' => 'ext-sfeventmgt-speaker',
                    'group' => 1
                ],
                [
                    'label' => 'administration.handleExpiredRegistrations',
                    'action' => 'handleExpiredRegistrations',
                    'icon' => 'ext-sfeventmgt-action-handle-expired',
                    'group' => 2
                ]
            ];
            foreach ($buttons as $key => $tableConfiguration) {
                $title = $this->getLanguageService()->sL(self::LANG_FILE . $tableConfiguration['label']);
                $link = $uriBuilder->reset()->setRequest($this->request)
                    ->uriFor($tableConfiguration['action'], [], 'Administration');
                $icon = $this->iconFactory->getIcon($tableConfiguration['icon'], Icon::SIZE_SMALL);
                $viewButton = $buttonBar->makeLinkButton()
                    ->setHref($link)
                    ->setDataAttributes([
                        'toggle' => 'tooltip',
                        'placement' => 'bottom',
                        'title' => $title
                        ])
                    ->setTitle($title)
                    ->setIcon($icon);
                $buttonBar->addButton($viewButton, ButtonBar::BUTTON_POSITION_LEFT, $tableConfiguration['group']);
            }
        }
    }

    /**
     * Redirect to tceform creating a new record
     *
     * @param string $table table name
     * @return void
     */
    private function redirectToCreateNewRecord($table)
    {
        $pid = $this->pid;
        $tsConfig = BackendUtility::getPagesTSconfig(0);
        if ($pid === 0 && isset($tsConfig['defaultPid.'])
            && is_array($tsConfig['defaultPid.'])
            && isset($tsConfig['defaultPid.'][$table])
        ) {
            $pid = (int)$tsConfig['defaultPid.'][$table];
        }

        if (MiscUtility::isV9Lts()) {
            $returnUrl = 'index.php?route=/web/SfEventMgtTxSfeventmgtM1/';
        } else {
            $returnUrl = 'index.php?M=web_SfEventMgtTxSfeventmgtM1';
        }

        $returnUrl .= '&id=' . $this->pid . $this->getToken();
        $url = BackendUtility::getModuleUrl('record_edit', [
            'edit[' . $table . '][' . $pid . ']' => 'new',
            'returnUrl' => $returnUrl
        ]);

        HttpUtility::redirect($url);
    }

    /**
     * Initialize action
     *
     * @return void
     */
    public function initializeAction()
    {
        $this->pid = (int)\TYPO3\CMS\Core\Utility\GeneralUtility::_GET('id');
    }

    /**
     * Set date format for fields startDate and endDate
     *
     * @return void
     */
    public function initializeListAction()
    {
        if ($this->settings === null || empty($this->settings)) {
            $this->redirect('settingsError');
        }
        $this->arguments->getArgument('searchDemand')
            ->getPropertyMappingConfiguration()->forProperty('startDate')
            ->setTypeConverterOption(
                DateTimeConverter::class,
                DateTimeConverter::CONFIGURATION_DATE_FORMAT,
                $this->settings['search']['dateFormat']
            );
        $this->arguments->getArgument('searchDemand')
            ->getPropertyMappingConfiguration()->forProperty('endDate')
            ->setTypeConverterOption(
                DateTimeConverter::class,
                DateTimeConverter::CONFIGURATION_DATE_FORMAT,
                $this->settings['search']['dateFormat']
            );
    }

    /**
     * List action for backend module
     *
     * @param \DERHANSEN\SfEventMgt\Domain\Model\Dto\SearchDemand $searchDemand SearchDemand
     * @param array $overwriteDemand OverwriteDemand
     *
     * @return void
     */
    public function listAction(SearchDemand $searchDemand = null, array $overwriteDemand = [])
    {
        if ($searchDemand !== null) {
            $searchDemand->setFields($this->settings['search']['fields']);

            $sessionData = [];
            $sessionData['searchDemand'] = $searchDemand;
            $sessionData['overwriteDemand'] = $overwriteDemand;
            $this->beUserSessionService->saveSessionData($sessionData);
        } else {
            // Try to restore search demand from Session
            $searchDemand = $this->beUserSessionService->getSessionDataByKey('searchDemand');
            $overwriteDemand = $this->beUserSessionService->getSessionDataByKey('overwriteDemand');
        }

        /** @var EventDemand $eventDemand */
        $eventDemand = $this->objectManager->get(EventDemand::class);
        $eventDemand = $this->overwriteEventDemandObject($eventDemand, $overwriteDemand ?? []);
        $eventDemand->setOrderFieldAllowed($this->settings['orderFieldAllowed']);
        $eventDemand->setSearchDemand($searchDemand);
        $eventDemand->setStoragePage($this->pid);
        $eventDemand->setIgnoreEnableFields(true);

        $events = [];
        if ($this->getBackendUser()->isInWebMount($this->pid)) {
            $events = $this->eventRepository->findDemanded($eventDemand);
        }

        $this->view->assignMultiple([
            'pid' => $this->pid,
            'events' => $events,
            'searchDemand' => $searchDemand,
            'orderByFields' => $this->getOrderByFields(),
            'orderDirections' => $this->getOrderDirections(),
            'overwriteDemand' => $overwriteDemand,
        ]);
    }

    /**
     * Export registrations for a given event
     *
     * @param int $eventUid Event UID
     *
     * @return void
     */
    public function exportAction($eventUid)
    {
        /** @var Event $event */
        $event = $this->eventRepository->findByUidIncludeHidden($eventUid);
        if ($event) {
            $this->checkEventAccess($event);
            $this->exportService->downloadRegistrationsCsv($eventUid, $this->settings['csvExport']);
        }
        exit();
    }

    /**
     * Handles expired registrations
     *
     * @return void
     */
    public function handleExpiredRegistrationsAction()
    {
        $delete = (bool)$this->settings['registration']['deleteExpiredRegistrations'];
        $this->maintenanceService->handleExpiredRegistrations($delete);

        $this->addFlashMessage(
            $this->getLanguageService()->sL(self::LANG_FILE . 'administration.message-1.content'),
            $this->getLanguageService()->sL(self::LANG_FILE . 'administration.message-1.title'),
            FlashMessage::OK
        );

        $this->redirect('list');
    }

    /**
     * The index notify action
     *
     * @param \DERHANSEN\SfEventMgt\Domain\Model\Event $event Event
     *
     * @return void
     */
    public function indexNotifyAction(Event $event)
    {
        $this->checkEventAccess($event);
        $customNotification = GeneralUtility::makeInstance(CustomNotification::class);
        $customNotifications = $this->settingsService->getCustomNotifications($this->settings);
        $logEntries = $this->customNotificationLogRepository->findByEvent($event);
        $this->view->assignMultiple([
            'event' => $event,
            'recipients' => $this->getNotificationRecipients(),
            'customNotification' => $customNotification,
            'customNotifications' => $customNotifications,
            'logEntries' => $logEntries,
        ]);
    }

    /**
     * Returns an array of recipient option for the indexNotify action
     *
     * @return array|array[]
     */
    public function getNotificationRecipients(): array
    {
        return [
            [
                'value' => CustomNotification::RECIPIENTS_ALL,
                'label' => $this->getLanguageService()->sL(
                    self::LANG_FILE . 'administration.notify.recipients.' . CustomNotification::RECIPIENTS_ALL
                )
            ],
            [
                'value' => CustomNotification::RECIPIENTS_CONFIRMED,
                'label' => $this->getLanguageService()->sL(
                    self::LANG_FILE . 'administration.notify.recipients.' . CustomNotification::RECIPIENTS_CONFIRMED
                )
            ],
            [
                'value' => CustomNotification::RECIPIENTS_UNCONFIRMED,
                'label' => $this->getLanguageService()->sL(
                    self::LANG_FILE . 'administration.notify.recipients.' . CustomNotification::RECIPIENTS_UNCONFIRMED
                )
            ],
        ];
    }

    /**
     * Create new event action
     */
    public function newEventAction()
    {
        $this->redirectToCreateNewRecord('tx_sfeventmgt_domain_model_event');
    }

    /**
     * Create new location action
     */
    public function newLocationAction()
    {
        $this->redirectToCreateNewRecord('tx_sfeventmgt_domain_model_location');
    }

    /**
     * Create new organisator action
     */
    public function newOrganisatorAction()
    {
        $this->redirectToCreateNewRecord('tx_sfeventmgt_domain_model_organisator');
    }

    /**
     * Create new speaker action
     */
    public function newSpeakerAction()
    {
        $this->redirectToCreateNewRecord('tx_sfeventmgt_domain_model_speaker');
    }

    /**
     * Notify action
     *
     * @param \DERHANSEN\SfEventMgt\Domain\Model\Event $event Event
     * @param \DERHANSEN\SfEventMgt\Domain\Model\Dto\CustomNotification $customNotification
     *
     * @return void
     */
    public function notifyAction(Event $event, CustomNotification $customNotification)
    {
        $this->checkEventAccess($event);
        $customNotifications = $this->settingsService->getCustomNotifications($this->settings);
        $result = $this->notificationService->sendCustomNotification($event, $customNotification, $this->settings);
        $this->notificationService->createCustomNotificationLogentry(
            $event,
            $customNotifications[$customNotification->getTemplate()],
            $result
        );
        $this->addFlashMessage(
            $this->getLanguageService()->sL(self::LANG_FILE . 'administration.message-2.content'),
            $this->getLanguageService()->sL(self::LANG_FILE . 'administration.message-2.title'),
            FlashMessage::OK
        );
        $this->redirect('list');
    }

    /**
     * Checks if the current backend user has access to the PID of the event and if not, enqueue an
     * access denied flash message and redirect to list view
     *
     * @param Event $event
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     */
    public function checkEventAccess(Event $event)
    {
        if ($this->getBackendUser()->isInWebMount($event->getPid()) === null) {
            $this->addFlashMessage(
                $this->getLanguageService()->sL(self::LANG_FILE . 'administration.accessdenied.content'),
                $this->getLanguageService()->sL(self::LANG_FILE . 'administration.accessdenied.title'),
                FlashMessage::ERROR
            );

            $this->redirect('list');
        }
    }

    /**
     * Shows the settings error view
     *
     * @return void
     */
    public function settingsErrorAction()
    {
    }

    /**
     * Suppress default validation messages
     *
     * @return bool
     */
    protected function getErrorFlashMessage()
    {
        return false;
    }

    /**
     * Get a CSRF token
     *
     * @param bool $tokenOnly Set it to TRUE to get only the token, otherwise including the &moduleToken= as prefix
     * @return string
     */
    protected function getToken(bool $tokenOnly = false): string
    {
        if (MiscUtility::isV9Lts()) {
            $tokenParameterName = 'token';
            $token = FormProtectionFactory::get('backend')->generateToken('route', 'web_SfEventMgtTxSfeventmgtM1');
        } else {
            $tokenParameterName = 'moduleToken';
            $token = FormProtectionFactory::get()->generateToken('moduleCall', 'web_SfEventMgtTxSfeventmgtM1');
        }

        if ($tokenOnly) {
            return $token;
        }

        return '&' . $tokenParameterName . '=' . $token;
    }

    /**
     * Returns the LanguageService
     *
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * Returns an array with possible order directions
     *
     * @return array
     */
    public function getOrderDirections()
    {
        return [
            'asc' => $this->getLanguageService()->sL(self::LANG_FILE . 'administration.sortOrder.asc'),
            'desc' => $this->getLanguageService()->sL(self::LANG_FILE . 'administration.sortOrder.desc')
        ];
    }

    /**
     * Returns an array with possible orderBy fields
     *
     * @return array
     */
    public function getOrderByFields()
    {
        return [
            'title' => $this->getLanguageService()->sL(self::LANG_FILE . 'administration.orderBy.title'),
            'startdate' => $this->getLanguageService()->sL(self::LANG_FILE . 'administration.orderBy.startdate'),
            'enddate' => $this->getLanguageService()->sL(self::LANG_FILE . 'administration.orderBy.enddate')
        ];
    }

    /**
     * Returns the Backend User
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
