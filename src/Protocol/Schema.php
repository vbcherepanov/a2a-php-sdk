<?php
declare(strict_types=1);
namespace A2A\Protocol;
final class Schema
{
    /** @var array<string, array{fields: array<string, array{type: string, repeated: bool, required: bool}>, groups: list<list<string>>}> */
    public const MESSAGES = array (
  'SendMessageConfiguration' => 
  array (
    'fields' => 
    array (
      'acceptedOutputModes' => 
      array (
        'type' => 'string',
        'repeated' => true,
        'required' => false,
      ),
      'taskPushNotificationConfig' => 
      array (
        'type' => 'TaskPushNotificationConfig',
        'repeated' => false,
        'required' => false,
      ),
      'historyLength' => 
      array (
        'type' => 'int32',
        'repeated' => false,
        'required' => false,
      ),
      'returnImmediately' => 
      array (
        'type' => 'bool',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'Task' => 
  array (
    'fields' => 
    array (
      'id' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'contextId' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'status' => 
      array (
        'type' => 'TaskStatus',
        'repeated' => false,
        'required' => true,
      ),
      'artifacts' => 
      array (
        'type' => 'Artifact',
        'repeated' => true,
        'required' => false,
      ),
      'history' => 
      array (
        'type' => 'Message',
        'repeated' => true,
        'required' => false,
      ),
      'metadata' => 
      array (
        'type' => 'google.protobuf.Struct',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'TaskStatus' => 
  array (
    'fields' => 
    array (
      'state' => 
      array (
        'type' => 'TaskState',
        'repeated' => false,
        'required' => true,
      ),
      'message' => 
      array (
        'type' => 'Message',
        'repeated' => false,
        'required' => false,
      ),
      'timestamp' => 
      array (
        'type' => 'google.protobuf.Timestamp',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'Part' => 
  array (
    'fields' => 
    array (
      'text' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'raw' => 
      array (
        'type' => 'bytes',
        'repeated' => false,
        'required' => false,
      ),
      'url' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'data' => 
      array (
        'type' => 'google.protobuf.Value',
        'repeated' => false,
        'required' => false,
      ),
      'metadata' => 
      array (
        'type' => 'google.protobuf.Struct',
        'repeated' => false,
        'required' => false,
      ),
      'filename' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'mediaType' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
      0 => 
      array (
        0 => 'text',
        1 => 'raw',
        2 => 'url',
        3 => 'data',
      ),
    ),
  ),
  'Message' => 
  array (
    'fields' => 
    array (
      'messageId' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'contextId' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'taskId' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'role' => 
      array (
        'type' => 'Role',
        'repeated' => false,
        'required' => true,
      ),
      'parts' => 
      array (
        'type' => 'Part',
        'repeated' => true,
        'required' => true,
      ),
      'metadata' => 
      array (
        'type' => 'google.protobuf.Struct',
        'repeated' => false,
        'required' => false,
      ),
      'extensions' => 
      array (
        'type' => 'string',
        'repeated' => true,
        'required' => false,
      ),
      'referenceTaskIds' => 
      array (
        'type' => 'string',
        'repeated' => true,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'Artifact' => 
  array (
    'fields' => 
    array (
      'artifactId' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'name' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'description' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'parts' => 
      array (
        'type' => 'Part',
        'repeated' => true,
        'required' => true,
      ),
      'metadata' => 
      array (
        'type' => 'google.protobuf.Struct',
        'repeated' => false,
        'required' => false,
      ),
      'extensions' => 
      array (
        'type' => 'string',
        'repeated' => true,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'TaskStatusUpdateEvent' => 
  array (
    'fields' => 
    array (
      'taskId' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'contextId' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'status' => 
      array (
        'type' => 'TaskStatus',
        'repeated' => false,
        'required' => true,
      ),
      'metadata' => 
      array (
        'type' => 'google.protobuf.Struct',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'TaskArtifactUpdateEvent' => 
  array (
    'fields' => 
    array (
      'taskId' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'contextId' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'artifact' => 
      array (
        'type' => 'Artifact',
        'repeated' => false,
        'required' => true,
      ),
      'append' => 
      array (
        'type' => 'bool',
        'repeated' => false,
        'required' => false,
      ),
      'lastChunk' => 
      array (
        'type' => 'bool',
        'repeated' => false,
        'required' => false,
      ),
      'metadata' => 
      array (
        'type' => 'google.protobuf.Struct',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'AuthenticationInfo' => 
  array (
    'fields' => 
    array (
      'scheme' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'credentials' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'AgentInterface' => 
  array (
    'fields' => 
    array (
      'url' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'protocolBinding' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'tenant' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'protocolVersion' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'AgentCard' => 
  array (
    'fields' => 
    array (
      'name' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'description' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'supportedInterfaces' => 
      array (
        'type' => 'AgentInterface',
        'repeated' => true,
        'required' => true,
      ),
      'provider' => 
      array (
        'type' => 'AgentProvider',
        'repeated' => false,
        'required' => false,
      ),
      'version' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'documentationUrl' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'capabilities' => 
      array (
        'type' => 'AgentCapabilities',
        'repeated' => false,
        'required' => true,
      ),
      'securitySchemes' => 
      array (
        'type' => 'map<string, SecurityScheme>',
        'repeated' => false,
        'required' => false,
      ),
      'securityRequirements' => 
      array (
        'type' => 'SecurityRequirement',
        'repeated' => true,
        'required' => false,
      ),
      'defaultInputModes' => 
      array (
        'type' => 'string',
        'repeated' => true,
        'required' => true,
      ),
      'defaultOutputModes' => 
      array (
        'type' => 'string',
        'repeated' => true,
        'required' => true,
      ),
      'skills' => 
      array (
        'type' => 'AgentSkill',
        'repeated' => true,
        'required' => true,
      ),
      'signatures' => 
      array (
        'type' => 'AgentCardSignature',
        'repeated' => true,
        'required' => false,
      ),
      'iconUrl' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'AgentProvider' => 
  array (
    'fields' => 
    array (
      'url' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'organization' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'AgentCapabilities' => 
  array (
    'fields' => 
    array (
      'streaming' => 
      array (
        'type' => 'bool',
        'repeated' => false,
        'required' => false,
      ),
      'pushNotifications' => 
      array (
        'type' => 'bool',
        'repeated' => false,
        'required' => false,
      ),
      'extensions' => 
      array (
        'type' => 'AgentExtension',
        'repeated' => true,
        'required' => false,
      ),
      'extendedAgentCard' => 
      array (
        'type' => 'bool',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'AgentExtension' => 
  array (
    'fields' => 
    array (
      'uri' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'description' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'required' => 
      array (
        'type' => 'bool',
        'repeated' => false,
        'required' => false,
      ),
      'params' => 
      array (
        'type' => 'google.protobuf.Struct',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'AgentSkill' => 
  array (
    'fields' => 
    array (
      'id' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'name' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'description' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'tags' => 
      array (
        'type' => 'string',
        'repeated' => true,
        'required' => true,
      ),
      'examples' => 
      array (
        'type' => 'string',
        'repeated' => true,
        'required' => false,
      ),
      'inputModes' => 
      array (
        'type' => 'string',
        'repeated' => true,
        'required' => false,
      ),
      'outputModes' => 
      array (
        'type' => 'string',
        'repeated' => true,
        'required' => false,
      ),
      'securityRequirements' => 
      array (
        'type' => 'SecurityRequirement',
        'repeated' => true,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'AgentCardSignature' => 
  array (
    'fields' => 
    array (
      'protected' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'signature' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'header' => 
      array (
        'type' => 'google.protobuf.Struct',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'TaskPushNotificationConfig' => 
  array (
    'fields' => 
    array (
      'tenant' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'id' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'taskId' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'url' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'token' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'authentication' => 
      array (
        'type' => 'AuthenticationInfo',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'StringList' => 
  array (
    'fields' => 
    array (
      'list' => 
      array (
        'type' => 'string',
        'repeated' => true,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'SecurityRequirement' => 
  array (
    'fields' => 
    array (
      'schemes' => 
      array (
        'type' => 'map<string, StringList>',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'SecurityScheme' => 
  array (
    'fields' => 
    array (
      'apiKeySecurityScheme' => 
      array (
        'type' => 'APIKeySecurityScheme',
        'repeated' => false,
        'required' => false,
      ),
      'httpAuthSecurityScheme' => 
      array (
        'type' => 'HTTPAuthSecurityScheme',
        'repeated' => false,
        'required' => false,
      ),
      'oauth2SecurityScheme' => 
      array (
        'type' => 'OAuth2SecurityScheme',
        'repeated' => false,
        'required' => false,
      ),
      'openIdConnectSecurityScheme' => 
      array (
        'type' => 'OpenIdConnectSecurityScheme',
        'repeated' => false,
        'required' => false,
      ),
      'mtlsSecurityScheme' => 
      array (
        'type' => 'MutualTlsSecurityScheme',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
      0 => 
      array (
        0 => 'apiKeySecurityScheme',
        1 => 'httpAuthSecurityScheme',
        2 => 'oauth2SecurityScheme',
        3 => 'openIdConnectSecurityScheme',
        4 => 'mtlsSecurityScheme',
      ),
    ),
  ),
  'APIKeySecurityScheme' => 
  array (
    'fields' => 
    array (
      'description' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'location' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'name' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'HTTPAuthSecurityScheme' => 
  array (
    'fields' => 
    array (
      'description' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'scheme' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'bearerFormat' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'OAuth2SecurityScheme' => 
  array (
    'fields' => 
    array (
      'description' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'flows' => 
      array (
        'type' => 'OAuthFlows',
        'repeated' => false,
        'required' => true,
      ),
      'oauth2MetadataUrl' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'OpenIdConnectSecurityScheme' => 
  array (
    'fields' => 
    array (
      'description' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'openIdConnectUrl' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'MutualTlsSecurityScheme' => 
  array (
    'fields' => 
    array (
      'description' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'OAuthFlows' => 
  array (
    'fields' => 
    array (
      'authorizationCode' => 
      array (
        'type' => 'AuthorizationCodeOAuthFlow',
        'repeated' => false,
        'required' => false,
      ),
      'clientCredentials' => 
      array (
        'type' => 'ClientCredentialsOAuthFlow',
        'repeated' => false,
        'required' => false,
      ),
      'implicit' => 
      array (
        'type' => 'ImplicitOAuthFlow',
        'repeated' => false,
        'required' => false,
      ),
      'password' => 
      array (
        'type' => 'PasswordOAuthFlow',
        'repeated' => false,
        'required' => false,
      ),
      'deviceCode' => 
      array (
        'type' => 'DeviceCodeOAuthFlow',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
      0 => 
      array (
        0 => 'authorizationCode',
        1 => 'clientCredentials',
        2 => 'implicit',
        3 => 'password',
        4 => 'deviceCode',
      ),
    ),
  ),
  'AuthorizationCodeOAuthFlow' => 
  array (
    'fields' => 
    array (
      'authorizationUrl' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'tokenUrl' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'refreshUrl' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'scopes' => 
      array (
        'type' => 'map<string, string>',
        'repeated' => false,
        'required' => true,
      ),
      'pkceRequired' => 
      array (
        'type' => 'bool',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'ClientCredentialsOAuthFlow' => 
  array (
    'fields' => 
    array (
      'tokenUrl' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'refreshUrl' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'scopes' => 
      array (
        'type' => 'map<string, string>',
        'repeated' => false,
        'required' => true,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'ImplicitOAuthFlow' => 
  array (
    'fields' => 
    array (
      'authorizationUrl' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'refreshUrl' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'scopes' => 
      array (
        'type' => 'map<string, string>',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'PasswordOAuthFlow' => 
  array (
    'fields' => 
    array (
      'tokenUrl' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'refreshUrl' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'scopes' => 
      array (
        'type' => 'map<string, string>',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'DeviceCodeOAuthFlow' => 
  array (
    'fields' => 
    array (
      'deviceAuthorizationUrl' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'tokenUrl' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'refreshUrl' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'scopes' => 
      array (
        'type' => 'map<string, string>',
        'repeated' => false,
        'required' => true,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'SendMessageRequest' => 
  array (
    'fields' => 
    array (
      'tenant' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'message' => 
      array (
        'type' => 'Message',
        'repeated' => false,
        'required' => true,
      ),
      'configuration' => 
      array (
        'type' => 'SendMessageConfiguration',
        'repeated' => false,
        'required' => false,
      ),
      'metadata' => 
      array (
        'type' => 'google.protobuf.Struct',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'GetTaskRequest' => 
  array (
    'fields' => 
    array (
      'tenant' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'id' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'historyLength' => 
      array (
        'type' => 'int32',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'ListTasksRequest' => 
  array (
    'fields' => 
    array (
      'tenant' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'contextId' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'status' => 
      array (
        'type' => 'TaskState',
        'repeated' => false,
        'required' => false,
      ),
      'pageSize' => 
      array (
        'type' => 'int32',
        'repeated' => false,
        'required' => false,
      ),
      'pageToken' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'historyLength' => 
      array (
        'type' => 'int32',
        'repeated' => false,
        'required' => false,
      ),
      'statusTimestampAfter' => 
      array (
        'type' => 'google.protobuf.Timestamp',
        'repeated' => false,
        'required' => false,
      ),
      'includeArtifacts' => 
      array (
        'type' => 'bool',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'ListTasksResponse' => 
  array (
    'fields' => 
    array (
      'tasks' => 
      array (
        'type' => 'Task',
        'repeated' => true,
        'required' => true,
      ),
      'nextPageToken' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'pageSize' => 
      array (
        'type' => 'int32',
        'repeated' => false,
        'required' => true,
      ),
      'totalSize' => 
      array (
        'type' => 'int32',
        'repeated' => false,
        'required' => true,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'CancelTaskRequest' => 
  array (
    'fields' => 
    array (
      'tenant' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'id' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'metadata' => 
      array (
        'type' => 'google.protobuf.Struct',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'GetTaskPushNotificationConfigRequest' => 
  array (
    'fields' => 
    array (
      'tenant' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'taskId' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'id' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'DeleteTaskPushNotificationConfigRequest' => 
  array (
    'fields' => 
    array (
      'tenant' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'taskId' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'id' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'SubscribeToTaskRequest' => 
  array (
    'fields' => 
    array (
      'tenant' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'id' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'ListTaskPushNotificationConfigsRequest' => 
  array (
    'fields' => 
    array (
      'tenant' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
      'taskId' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => true,
      ),
      'pageSize' => 
      array (
        'type' => 'int32',
        'repeated' => false,
        'required' => false,
      ),
      'pageToken' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'GetExtendedAgentCardRequest' => 
  array (
    'fields' => 
    array (
      'tenant' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
  'SendMessageResponse' => 
  array (
    'fields' => 
    array (
      'task' => 
      array (
        'type' => 'Task',
        'repeated' => false,
        'required' => false,
      ),
      'message' => 
      array (
        'type' => 'Message',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
      0 => 
      array (
        0 => 'task',
        1 => 'message',
      ),
    ),
  ),
  'StreamResponse' => 
  array (
    'fields' => 
    array (
      'task' => 
      array (
        'type' => 'Task',
        'repeated' => false,
        'required' => false,
      ),
      'message' => 
      array (
        'type' => 'Message',
        'repeated' => false,
        'required' => false,
      ),
      'statusUpdate' => 
      array (
        'type' => 'TaskStatusUpdateEvent',
        'repeated' => false,
        'required' => false,
      ),
      'artifactUpdate' => 
      array (
        'type' => 'TaskArtifactUpdateEvent',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
      0 => 
      array (
        0 => 'task',
        1 => 'message',
        2 => 'statusUpdate',
        3 => 'artifactUpdate',
      ),
    ),
  ),
  'ListTaskPushNotificationConfigsResponse' => 
  array (
    'fields' => 
    array (
      'configs' => 
      array (
        'type' => 'TaskPushNotificationConfig',
        'repeated' => true,
        'required' => false,
      ),
      'nextPageToken' => 
      array (
        'type' => 'string',
        'repeated' => false,
        'required' => false,
      ),
    ),
    'groups' => 
    array (
    ),
  ),
);
}
