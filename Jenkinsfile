pipeline {
    agent any

    environment {
        APP_ENV = 'testing'
        DB_CONNECTION = 'sqlite'
        DB_DATABASE = ':memory:'
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Install Dependencies') {
            steps {
                sh 'composer install --no-interaction --prefer-dist --optimize-autoloader'
                sh 'npm ci'
            }
        }

        stage('Lint') {
            parallel {
                stage('PHP CS Fixer') {
                    steps {
                        sh 'vendor/bin/pint --test'
                    }
                }
                stage('PHPStan') {
                    steps {
                        sh 'vendor/bin/phpstan analyse --memory-limit=2G'
                    }
                }
            }
        }

        stage('Tests') {
            parallel {
                stage('Unit & Feature') {
                    steps {
                        sh 'php artisan config:clear'
                        sh 'php artisan test --parallel'
                    }
                }
                stage('Static Analysis') {
                    steps {
                        sh 'php artisan ide-helper:generate'
                        sh 'php artisan ide-helper:models -N'
                    }
                }
            }
        }

        stage('Build') {
            steps {
                sh 'php artisan optimize'
                sh 'php artisan lighthouse:cache'
                sh 'php artisan scribe:generate'
                sh 'npm run build'
            }
        }

        stage('Deploy') {
            when {
                branch 'main'
            }
            steps {
                sh 'docker build -t nexi-erp:latest .'
                sh 'docker tag nexi-erp:latest registry.example.com/nexi-erp:${BUILD_NUMBER}'
                sh 'docker push registry.example.com/nexi-erp:${BUILD_NUMBER}'
                // Kubernetes deploy step
                sh 'kubectl set image deployment/nexi-erp app=registry.example.com/nexi-erp:${BUILD_NUMBER} --record'
            }
        }
    }

    post {
        always {
            junit 'storage/logs/test-results/*.xml'
            archiveArtifacts artifacts: 'storage/logs/*.log', allowEmptyArchive: true
        }
        failure {
            slackSend(
                color: '#FF0000',
                message: "Pipeline failed: ${env.JOB_NAME} [${env.BUILD_NUMBER}]"
            )
        }
        success {
            slackSend(
                color: '#00FF00',
                message: "Pipeline succeeded: ${env.JOB_NAME} [${env.BUILD_NUMBER}]"
            )
        }
    }
}
