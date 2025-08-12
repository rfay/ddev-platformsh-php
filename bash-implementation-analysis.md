# Bash Implementation Analysis

## Current Structure Overview

### Bash Scripts in platformsh/

All scripts follow the same pattern: take relationship name as input, output JSON stanza using HERE document.

#### 1. generate_db_relationship.sh
**Input**: `$1=dbservice`, `$2=dbtype`, `$3=relationshipname`
**Output**: Database relationship JSON with proper scheme/port mapping
**Logic**: 
- Maps database types (mariadb/mysql → mysql+3306, postgres → pgsql+5432)
- Generates standardized database relationship structure
- Uses variables for scheme, port, and rel fields

#### 2. generate_route.sh  
**Input**: `$1=route`, `$2=id`, `$3=production_url`, `$4=upstream`, `$5=type`, `$6=original_url`
**Output**: Route configuration JSON
**Logic**: 
- Handles optional id field (null if empty)
- Creates route stanza with all Platform.sh route properties

#### 3. generate_redis_relationship.sh
**Input**: `$1=relationshipname`
**Output**: Redis service relationship JSON
**Logic**: 
- Hardcoded Redis configuration (port 6379, type redis:6.0)
- Standard service relationship structure

#### 4. generate_redis-persistent_relationship.sh
**Input**: `$1=relationshipname`  
**Output**: Identical to redis_relationship.sh
**Logic**: Same as redis (persistent storage handled elsewhere)

#### 5. generate_elasticsearch_relationship.sh
**Input**: `$1=relationshipname`
**Output**: Elasticsearch service relationship JSON
**Logic**: 
- HTTP scheme, port 9200, type elasticsearch:7.5
- Maps to 'elasticsearch' hostname in DDEV

#### 6. generate_opensearch_relationship.sh  
**Input**: `$1=relationshipname`
**Output**: Identical to elasticsearch_relationship.sh  
**Logic**: Same structure as Elasticsearch (compatibility mapping)

#### 7. generate_memcached_relationship.sh
**Input**: `$1=relationshipname` 
**Output**: Memcached service relationship JSON
**Logic**: 
- Memcached scheme, port 11211, type memcached:1.6
- Simpler structure (fewer fields than other services)

## Go Templating Patterns in install.yaml

### yaml_read_files Configuration
```yaml
yaml_read_files:
  platformapp: .platform.app.yaml
  services: .platform/services.yaml  
  routes: .platform/routes.yaml
```

### Key Template Variables
- `.platformapp.*` - Platform.sh application configuration
- `.services.*` - Platform.sh services definition
- `.routes.*` - Platform.sh routes configuration  
- `.DdevGlobalConfig.*` - DDEV global configuration
- `.DdevProjectConfig.*` - DDEV project configuration

### Complex Logic Patterns

#### 1. Application Type Validation
```go
{{ if not (hasPrefix "php" .platformapp.type) }}
  exit 5 // Unsupported application type
{{ end }}
```

#### 2. Environment Variable Management
```go
{{ contains "PLATFORMSH_CLI_TOKEN" (list .DdevGlobalConfig.web_environment | toString) }}
```
Checks if environment variables already exist before prompting user

#### 3. PHP Dependencies Processing  
```go
{{ if .platformapp.dependencies.php }}
  {{ range $pkg, $version := .platformapp.dependencies.php }}
    {{ if ne $pkg "composer/composer" }}
      RUN composer global require {{ $pkg }}:{{ $version }}
    {{ end }}
  {{ end }}
{{ end }}
```

#### 4. Routes Processing
```go
{{ range $k, $v := .routes }}
  {{ $id := "" }}{{ if $v.id }}{{ $id = $v.id }}{{ end }}
  r=$(./platformsh/generate_route.sh "${DDEV_PRIMARY_URL}/" '{{ $id }}' ...)
  routes+=(${r})
{{ end }}
```
Iterates over routes, calls bash script, collects base64-encoded results

#### 5. Services/Relationships Processing (Most Complex)
```go
{{ range $relationship_name, $relationship_def := .platformapp.relationships }}
  {{ $service_name := index (split ":" $relationship_def) "_0" }}
  {{ $service_def := get $.services $service_name }}
  {{ $service_type := $service_def.type | split ":" }}
  
  // Database type mapping
  {{ if eq $service_def.type "mysql" }}{{ $_ = set $service_def "type" "mariadb" }}{{ end }}
  {{ if eq $service_def.type "postgresql" }}{{ $_ = set $service_def "type" "postgres" }}{{ end }}
  
  // Call appropriate bash script based on service type
  {{ if $supported_db_types | has $service_def.type }}
    relationships+=($(./platformsh/generate_db_relationship.sh ...))
  {{ else if hasKey $supported_services $service_def.type }}
    relationships+=($(./platformsh/generate_{{ $service_def.type }}_relationship.sh ...))
    ddev add-on get {{ get $supported_services $service_def.type }}
  {{ end }}
{{ end }}
```

#### 6. PHP Version and Extensions Processing
```go
{{ $phpversion := trimPrefix "php:" .platformapp.type }}
{{ $phpextensions := without .platformapp.runtime.extensions "blackfire" "pdo_pgsql" "sodium" }}
{{ range $extension := $phpextensions }}
  - php{{ $phpversion }}-{{ $extension }}
{{ end }}
```

#### 7. Hooks Processing
```go  
{{ if .platformapp.hooks.build }}
  {{ $noblanks := regexReplaceAll "\n\n*" .platformapp.hooks.build "\n" }}
  - exec: |
{{ indent 6 $noblanks }}
{{ end }}
```

## Data Flow Analysis

### Input Sources
1. **Platform.sh Config Files**: `.platform.app.yaml`, `.platform/services.yaml`, `.platform/routes.yaml`
2. **DDEV Configuration**: Project and global config via template variables
3. **User Input**: Platform.sh tokens and project IDs via interactive prompts
4. **Environment**: DDEV environment variables and project details

### Processing Steps
1. **Validation**: Check PHP application type
2. **User Input Collection**: Get Platform.sh credentials if not present
3. **Dependencies Installation**: Composer and Python packages from Platform.sh config
4. **Routes Generation**: Process routes and call generate_route.sh
5. **Relationships Generation**: Complex service mapping and bash script calls
6. **Configuration Generation**: Create config.platformsh.yaml with computed values
7. **Environment Variables**: Set PLATFORM_* variables with base64-encoded JSON

### Output Artifacts  
1. **Dockerfile Additions**: `web-build/Dockerfile.platformsh`
2. **DDEV Configuration**: `config.platformsh.yaml`
3. **Environment Files**: `.ddev/web-entrypoint.d/environment.sh`
4. **Service Add-ons**: Automatic installation of ddev-redis, ddev-elasticsearch, etc.

## Bash Commands Requiring PHP Equivalents

### File Operations
- `cat <<EOF >file` → `file_put_contents()`
- `mkdir -p` → `mkdir($path, 0755, true)`
- `cp source dest` → `copy($source, $dest)`
- `rm -f file` → `unlink($file)`

### String/Data Processing  
- `base64 -w 0` → `base64_encode()`
- `base64 -d` → `base64_decode()`
- `jq` operations → `yaml_parse_file()` + PHP array operations
- HERE documents → PHP array structures + `yaml_emit()`

### Environment/System
- `echo $VARIABLE` → `$_ENV['VARIABLE']`
- `ddev describe -j` → Use processed configuration files
- `ddev debug get-volume-db-version` → Parse from configuration or environment
- `ddev config --web-environment-add` → Modify configuration files

### Complex Logic
- Go template conditionals → PHP if/else statements
- Go template loops → PHP foreach loops  
- Go template functions → PHP string/array functions
- Variable assignments and scoping → PHP variables

## install.yaml Sections for PHP Conversion

### 1. Pre-install Actions (Lines 20-97)
**Current**: Go template conditionals and user input prompts
**PHP Approach**: 
- Read processed configuration to check existing environment variables
- Use environment variables or configuration files for user input
- Eliminate `ddev config` calls by writing configuration directly

### 2. Dockerfile Generation (Lines 102-114)
**Current**: Go template loops with cat heredoc
**PHP Approach**:
- Parse Platform.sh dependencies directly with `yaml_parse_file()`
- Use PHP string building or templates for Dockerfile content
- Write directly to `web-build/Dockerfile.platformsh`

### 3. Main Configuration Logic (Lines 142-227)
**Current**: Complex bash with Go template integration, base64 encoding
**PHP Approach**:
- Direct YAML parsing of Platform.sh files
- PHP array manipulation for routes and relationships
- Use `yaml_emit()` for clean JSON generation
- Replace base64 operations with direct PHP array handling

### 4. Config File Generation (Lines 238-317)
**Current**: Here document with Go template interpolation  
**PHP Approach**:
- Create PHP array structure for configuration
- Use `yaml_emit()` to generate clean YAML output
- Direct file writing with proper DDEV configuration structure

## Translation Advantages with PHP Add-on Framework

### Environment Variables Available
- `$_ENV['DDEV_PROJECT']`, `$_ENV['DDEV_DOCROOT']`, `$_ENV['DDEV_PROJECT_TYPE']`
- `$_ENV['DDEV_PHP_VERSION']`, `$_ENV['DDEV_DATABASE']`
- Eliminates need for `ddev describe` and config parsing

### Processed Configuration Access  
- `.ddev-config/project_config.yaml` - Complete project configuration
- `.ddev-config/global_config.yaml` - Global DDEV settings
- No need for complex Go template variable access

### Direct Platform.sh Access
- Read `.platform.app.yaml` directly with `yaml_parse_file()`  
- Process services and routes without template complexity
- Clean PHP logic instead of bash + template hybrid

### Simplified Data Handling
- PHP arrays instead of base64-encoded JSON strings
- Direct JSON generation with proper escaping
- No need for external base64/jq tools

## Key Translation Challenges

### User Input Collection
- Current: Interactive bash prompts with `read`
- PHP Solution: Environment variables or pre-execution configuration

### Service Add-on Installation  
- Current: `ddev add-on get` calls during processing
- PHP Solution: Document required add-ons, install via hooks or external process

### Database Version Validation
- Current: `ddev debug get-volume-db-version`  
- PHP Solution: Use processed configuration or environment variables

### Configuration Writing
- Current: `ddev config --web-environment-add` 
- PHP Solution: Direct YAML file modification or documented manual steps