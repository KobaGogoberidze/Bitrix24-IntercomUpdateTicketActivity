<?

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Bizproc\FieldType;
use Bitrix\Main\Localization\Loc;
use \Bitrix\Main\DI\ServiceLocator;
use Local\Exceptions\IntercomException;

class CBPIntercomUpdateTicketActivity extends CBPActivity
{
    /**
     * Initialize activity
     * 
     * @param string $name
     */
    public function __construct($name)
    {
        parent::__construct($name);

        $this->arProperties = [
            "Title" => "",
            "TicketId" => null,
            "TicketState" => null,
        ];
    }
    /**
     * Start the execution of activity
     * 
     * @return CBPActivityExecutionStatus
     */
    public function Execute()
    {
        $validationErrors = self::ValidateProperties(array_map(
            fn ($property) => $this->{$property["FieldName"]},
            self::getPropertiesDialogMap()
        ));

        if (!empty($validationErrors)) {
            foreach ($validationErrors as $error) {
                $this->WriteToTrackingService($error["message"], 0, CBPTrackingType::Error);
            }
            return CBPActivityExecutionStatus::Closed;
        }

        $serviceLocator = ServiceLocator::getInstance();

        if ($serviceLocator->has("intercom")) {
            $client = ServiceLocator::getInstance()->get("intercom");
            try {
                $client->tickets->update(($this->TicketId + 1), [
                    "state" => $this->TicketState
                ]);
            } catch (IntercomException $e) {
                foreach ($e->getMessages() as $message) {
                    $this->WriteToTrackingService($message, 0, CBPTrackingType::Error);
                }
            }
        } else {
            $this->WriteToTrackingService(Loc::getMessage("INTERCOM_UT_UNABLE_TO_LOCATE_INTERCOM_SERVICE"), 0, CBPTrackingType::Error);
        }

        return CBPActivityExecutionStatus::Closed;
    }

    /**
     * Generate setting form
     * 
     * @param array $documentType
     * @param string $activityName
     * @param array $workflowTemplate
     * @param array $workflowParameters
     * @param array $workflowVariables
     * @param array $currentValues
     * @param string $formName
     * @return string
     */
    public static function GetPropertiesDialog($documentType, $activityName, $workflowTemplate, $workflowParameters, $workflowVariables, $currentValues = null, $formName = "", $popupWindow = null, $siteId = "")
    {
        $dialog = new \Bitrix\Bizproc\Activity\PropertiesDialog(__FILE__, [
            "documentType" => $documentType,
            "activityName" => $activityName,
            "workflowTemplate" => $workflowTemplate,
            "workflowParameters" => $workflowParameters,
            "workflowVariables" => $workflowVariables,
            "currentValues" => $currentValues,
            "formName" => $formName,
            "siteId" => $siteId
        ]);
        $dialog->setMap(static::getPropertiesDialogMap($documentType));

        return $dialog;
    }

    /**
     * Process form submition
     * 
     * @param array $documentType
     * @param string $activityName
     * @param array &$workflowTemplate
     * @param array &$workflowParameters
     * @param array &$workflowVariables
     * @param array &$currentValues
     * @param array &$errors
     * @return bool
     */
    public static function GetPropertiesDialogValues($documentType, $activityName, &$workflowTemplate, &$workflowParameters, &$workflowVariables, $currentValues, &$errors)
    {
        $documentService = CBPRuntime::GetRuntime(true)->getDocumentService();
        $dialog = new \Bitrix\Bizproc\Activity\PropertiesDialog(__FILE__, [
            "documentType" => $documentType,
            "activityName" => $activityName,
            "workflowTemplate" => $workflowTemplate,
            "workflowParameters" => $workflowParameters,
            "workflowVariables" => $workflowVariables,
            "currentValues" => $currentValues,
        ]);

        $properties = [];
        foreach (static::getPropertiesDialogMap($documentType) as $propertyKey => $propertyAttributes) {
            $field = $documentService->getFieldTypeObject($dialog->getDocumentType(), $propertyAttributes);
            if (!$field) {
                continue;
            }

            $properties[$propertyKey] = $field->extractValue(
                ["Field" => $propertyAttributes["FieldName"]],
                $currentValues,
                $errors
            );
        }

        $errors = static::ValidateProperties($properties, new CBPWorkflowTemplateUser(CBPWorkflowTemplateUser::CurrentUser));

        if (count($errors) > 0) {
            return false;
        }

        $currentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($workflowTemplate, $activityName);
        $currentActivity["Properties"] = $properties;

        return true;
    }

    /**
     * Validate user provided properties
     * 
     * @param array $testProperties
     * @param CBPWorkflowTemplateUser $user
     * @return array
     */
    public static function ValidateProperties($testProperties = [], CBPWorkflowTemplateUser $user = null)
    {
        $errors = [];

        foreach (static::getPropertiesDialogMap() as $propertyKey => $propertyAttributes) {
            if (CBPHelper::getBool($propertyAttributes['Required']) && CBPHelper::isEmptyValue($testProperties[$propertyKey])) {
                $errors[] = [
                    "code" => "emptyText",
                    "parameter" => $propertyKey,
                    "message" => Loc::getMessage("INTERCOM_UT_FIELD_NOT_SPECIFIED", ["#FIELD_NAME#" => $propertyAttributes["Name"]])
                ];
            }
        }

        return array_merge($errors, parent::ValidateProperties($testProperties, $user));
    }

    /** 
     * Get ticket states map
     * 
     * @return array
     */
    protected static function getTicketStatesMap()
    {
        return [
            "in_progress" => Loc::GetMessage("INTERCOM_UT_TICKET_STATE_IN_PROGRESS"),
            "waiting_on_customer" => Loc::GetMessage("INTERCOM_UT_TICKET_STATE_WAITING_ON_CUSTOMER"),
            "resolved" => Loc::GetMessage("INTERCOM_UT_TICKET_STATE_RESOLVED"),
        ];
    }

    /**
     * User provided properties
     * 
     * @return array
     */
    private static function getPropertiesDialogMap()
    {
        return [
            "TicketId" => [
                "Name" => Loc::GetMessage("INTERCOM_UT_TICKET_ID_FIELD"),
                "FieldName" => "TicketId",
                "Type" => FieldType::INT,
                "Required" => true
            ],
            "TicketState" => [
                "Name" => Loc::GetMessage("INTERCOM_UT_TICKET_STATE_FIELD"),
                "FieldName" => "TicketState",
                "Type" => FieldType::SELECT,
                "Options" => static::getTicketStatesMap(),
                "Required" => true,
                "Default" => "in_progress"
            ]
        ];
    }
}
